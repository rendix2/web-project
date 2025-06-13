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
use Nettrine\ORM\EntityManagerDecorator;

class LoginPresenter extends Presenter
{

    public function __construct(
        private readonly Translator $translator,
        private readonly EntityManagerDecorator $em,
        private readonly UsernameAndPasswordAuthenticator $usernameAndPasswordAuthenticator,
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

        $form->setTranslator($this->translator);
        $form->addProtection('Please try again.');
        BootstrapForm::switchBootstrapVersion(BootstrapVersion::V5);

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
        try {
            $this->getUser()->setAuthenticator($this->usernameAndPasswordAuthenticator);
            $this->getUser()->login(
                $form->getValues()['username'],
                $form->getHttpData()['password']
            );

            if ($form->getValues()['stayLoggedIn']) {
                $autoLoginToken = Random::generate(128);

                $this->getHttpResponse()->setCookie(AutoLoginAuthenticator::COOKIE_NAME, $autoLoginToken, null);

                $userEntity = $this->em
                    ->getRepository(UserEntity::class)
                    ->findOneBy(
                        [
                            'id' => $this->getUser()->getId(),
                            'username' => $form->getValues()['username'],

                        ]
                    );

                $userAutoLoginEntity = new UserAutoLoginEntity();
                $userAutoLoginEntity->user = $userEntity;
                $userAutoLoginEntity->token = $autoLoginToken;
                $userAutoLoginEntity->setIpAddress($this->getHttpRequest()->getRemoteAddress());

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
            $this->flashMessage($exception->getMessage(), 'danger');
            $this->redrawControl('flashes');
        }
    }

}
