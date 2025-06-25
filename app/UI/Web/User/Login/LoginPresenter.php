<?php declare(strict_types=1);

namespace App\UI\Web\User\Login;

use App\Core\AutoLoginAuthenticator;
use App\Core\UsernameAndPasswordAuthenticator;
use App\Model\Entity\UserAutoLoginEntity;
use App\Model\Entity\UserEntity;
use Contributte\FormsBootstrap\BootstrapForm;
use Contributte\FormsBootstrap\Enums\BootstrapVersion;
use Doctrine\DBAL\Exception as DbalException;
use Nette\Application\UI\Form;
use Nette\Application\UI\Presenter;
use Nette\Localization\Translator;
use Nette\Security\AuthenticationException;
use Nette\Utils\Random;
use App\Database\EntityManagerDecorator;

class LoginPresenter extends Presenter
{

    public function __construct(
        private readonly Translator $translator,
        private readonly EntityManagerDecorator $em,
        private readonly UsernameAndPasswordAuthenticator $usernameAndPasswordAuthenticator,
        private readonly UserLoginAttemptCheckService $userLoginAttemptCheckService,
    )
    {
    }

    public function renderDefault() : void
    {
        if ($this->getUser()->isLoggedIn()) {
            $this->flashMessage('You are already logged in', 'danger');
            $this->redrawControl('flashes');
        }
    }

    public function createComponentLoginForm() : BootstrapForm
    {
        $form = new BootstrapForm();
        BootstrapForm::switchBootstrapVersion(BootstrapVersion::V5);

        $form->setTranslator($this->translator);
        $form->addProtection('Please try again.');

        $form->addText('username', 'web-user-login.form.username.label')
            ->setRequired('web-user-login.form.username.required')
            ->setMaxLength(512);

        $form->addPassword('password', 'web-user-login.form.password.label')
            ->setRequired('web-user-login.form.password.required');

        $form->addCheckbox('stayLoggedIn', 'web-user-login.form.stayLoggedIn');

        $submitButton = $form->addSubmit('login', 'web-user-login.form.submit.name')
            ->setHtmlAttribute('class', 'mt-2');

        if ($this->getUser()->isLoggedIn()) {
            $submitButton
                ->setDisabled();
        }

        $form->onSuccess[] = [$this, 'loginFormSuccess'];

        return $form;
    }

    public function loginFormSuccess(Form $form) : void
    {
        $username = $form->getValues()['username'];
        $password = $form->getHttpData()['password'];
        $stayLoggedIn = $form->getValues()['stayLoggedIn'];
        $ip = $this->getHttpRequest()->getRemoteAddress();

        try {
            if ($this->userLoginAttemptCheckService->isIpBlocked($ip)) {
                $this->flashMessage('Z této IP adresy je příliš mnoho pokusů. Zkuste to později.', 'danger');
                return;
            }

            if ($this->userLoginAttemptCheckService->isUserNameBlocked($username)) {
                $this->flashMessage('Tento účet je dočasně zablokován. Zkuste to prosím později.', 'danger');
                return;
            }

            $this->getUser()->setAuthenticator($this->usernameAndPasswordAuthenticator);
            $this->getUser()->login($username, $password);
            $this->getUser()->setExpiration('2 hours');

            $this->userLoginAttemptCheckService->clearAttempts($username, $ip);

            if ($stayLoggedIn) {
                $autoLoginToken = Random::generate(128);

                $this->getHttpResponse()->setCookie(AutoLoginAuthenticator::COOKIE_NAME, $autoLoginToken, null);

                $userEntity = $this->em
                    ->getRepository(UserEntity::class)
                    ->findOneBy(
                        [
                            'id' => $this->getUser()->getId(),
                            'username' => $username,
                        ]
                    );

                if (!$userEntity) {
                    throw new AuthenticationException('Uživatel nebyl nalezen po přihlášení.');
                }

                $userAutoLoginEntity = new UserAutoLoginEntity();
                $userAutoLoginEntity->user = $userEntity;
                $userAutoLoginEntity->token = $autoLoginToken;
                $userAutoLoginEntity->ipAddress = $ip;

                try {
                    $this->em->persist($userAutoLoginEntity);
                    $this->em->flush();
                } catch (DbalException $exception) {
                    $this->flashMessage($exception->getMessage(), 'danger');
                    $this->redrawControl('flashes');
                }
            }

            $this->flashMessage(
                $this->translator->translate('web-user-login.form.submit.success'),
                'success'
            );
            $this->redirect(':Web:Home:default');
        } catch (AuthenticationException $exception) {
            $this->userLoginAttemptCheckService->logAttempt($username, $ip);
            $this->flashMessage($exception->getMessage(), 'danger');
            $this->redrawControl('flashes');
        }
    }

}
