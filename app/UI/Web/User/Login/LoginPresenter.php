<?php declare(strict_types=1);

namespace App\UI\Web\User\Login;

use App\Core\AutoLoginAuthenticator;
use App\Core\UsernameAndPasswordAuthenticator;
use App\Model\Entity\UserAutoLoginEntity;
use App\Model\Entity\UserEntity;
use App\Service\GeoIpService;
use Contributte\FormsBootstrap\BootstrapForm;
use Contributte\FormsBootstrap\Enums\BootstrapVersion;
use Doctrine\DBAL\Exception as DbalException;
use donatj\UserAgent\UserAgentParser;
use Nette\Application\UI\Form;
use Nette\Application\UI\Presenter;
use Nette\Localization\Translator;
use Nette\Security\AuthenticationException;
use Nette\Utils\Random;
use App\Database\EntityManagerDecorator;

class LoginPresenter extends Presenter
{

    public function __construct(
        private readonly Translator                       $translator,
        private readonly EntityManagerDecorator           $em,
        private readonly UsernameAndPasswordAuthenticator $usernameAndPasswordAuthenticator,
        private readonly GeoIpService                     $geoIpService,
    )
    {
        parent::__construct();
    }

    public function renderDefault() : void
    {
        if ($this->getUser()->isLoggedIn()) {
            $this->flashMessage($this->translator->translate('web-user-login.alreadyLoggedIn'), 'danger');
            $this->redrawControl('flashes');
        }
    }

    public function createComponentLoginForm() : BootstrapForm
    {
        $form = new BootstrapForm();
        BootstrapForm::switchBootstrapVersion(BootstrapVersion::V5);

        $form->setTranslator($this->translator);

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
        $values = $form->getValues(LoginValues::class);

        $username = $values->username;
        $password = $values->password;
        $stayLoggedIn = $values->stayLoggedIn;
        $ip = $this->getHttpRequest()->getRemoteAddress();

        try {
            $this->getUser()->setAuthenticator($this->usernameAndPasswordAuthenticator);
            $this->getUser()->login($username, $password);
            // Bez "zůstat přihlášen" ať sezení skutečně skončí brzy - s ním ho drží při životě
            // hlavně autologin cookie/entita níže, tohle je jen limit té samotné session. Nette
            // hlásí (fatální kvůli tracy.strictMode) chybu, pokud je tahle hodnota delší než
            // session.expiration v config/common.neon (14 dní) - proto ne '30 days'.
            $this->getUser()->setExpiration($stayLoggedIn ? '14 days' : '2 hours');

            if ($stayLoggedIn) {
                $autoLoginToken = Random::generate(128);

                // Cookie musí přežít zavření prohlížeče (to je smysl "zůstat přihlášen") - session
                // cookie (expire === null) mizí spolu se session, takže by nikdy nic neobnovila.
                $this->getHttpResponse()->setCookie(AutoLoginAuthenticator::COOKIE_NAME, $autoLoginToken, '30 days');

                $userEntity = $this->em
                    ->getRepository(UserEntity::class)
                    ->findOneBy(
                        [
                            'id' => $this->getUser()->getId(),
                            'username' => $username,
                        ]
                    );

                if ($userEntity === null) {
                    throw new AuthenticationException('Uživatel nebyl nalezen po přihlášení.');
                }

                if ($ip === null) {
                    $geoIpCountry = 'Unknown';
                    $ip = 'Unknown';
                } else {
                    $geoIpArray = $this->geoIpService->getInfo($ip);

                    $geoIpCountry = $geoIpArray['country'];
                }

                $parser = new UserAgentParser();
                $ua = $parser->parse();

                $userAutoLoginEntity = new UserAutoLoginEntity();
                $userAutoLoginEntity->user = $userEntity;
                $userAutoLoginEntity->token = $autoLoginToken;
                $userAutoLoginEntity->ipAddress = $ip;
                $userAutoLoginEntity->countryCode = $geoIpCountry;
                $userAutoLoginEntity->userAgent = $ua->browser();

                try {
                    $this->em->persist($userAutoLoginEntity);
                    $this->em->flush();
                } catch (DbalException $exception) {
                    bdump($exception);
                    $this->flashMessage($this->translator->translate('web-user-login.form.submit.autoLoginError'), 'danger');
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
