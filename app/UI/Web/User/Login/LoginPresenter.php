<?php declare(strict_types=1);

namespace App\UI\Web\User\Login;

use stdClass;
use App\Core\AutoLoginAuthenticator;
use App\Core\AutoLoginTokenService;
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
use App\Database\EntityManagerDecorator;
use App\Forms\IconBootstrapForm;
use App\Forms\Renderer\IconBootstrapRenderer;

class LoginPresenter extends Presenter
{

    public function __construct(
        private readonly Translator                       $translator,
        private readonly EntityManagerDecorator           $em,
        private readonly UsernameAndPasswordAuthenticator $usernameAndPasswordAuthenticator,
        private readonly GeoIpService                     $geoIpService,
        private readonly AutoLoginTokenService            $autoLoginTokens,
    )
    {
        parent::__construct();
    }

    public function renderDefault() : void
    {
        if ($this->getUser()->isLoggedIn()) {
            $flash = new stdClass();
            $flash->place = 'general';
            $flash->type = 'danger';
            $flash->message = $this->translator->translate('web-user-login.alreadyLoggedIn');

            $this->flashMessage($flash);
            $this->redrawControl('flashes');
        }
    }

    public function createComponentLoginForm() : IconBootstrapForm
    {
        $form = new IconBootstrapForm();
        BootstrapForm::switchBootstrapVersion(BootstrapVersion::V5);

        $form->setTranslator($this->translator);

        $form->addText('username', 'web-user-login.form.username.label')
            ->setRequired('web-user-login.form.username.required')
            ->setMaxLength(512)
            ->setOption(IconBootstrapRenderer::ICON, 'fas fa-user');

        $form->addPassword('password', 'web-user-login.form.password.label')
            ->setRequired('web-user-login.form.password.required')
            ->setOption(IconBootstrapRenderer::ICON, 'fas fa-lock');

        $form->addCheckbox('stayLoggedIn', 'web-user-login.form.stayLoggedIn');

        $submitButton = $form->addSubmit('login', 'web-user-login.form.submit.name')
            ->setHtmlAttribute('class', 'mt-2')
            ->setOption(IconBootstrapRenderer::ICON, 'fas fa-sign-in-alt');

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
                $userEntity = $this->em
                    ->getRepository(UserEntity::class)
                    ->findOneBy(
                        [
                            'id' => $this->getUser()->getId(),
                        ]
                    );

                if ($userEntity === null) {
                    throw new AuthenticationException('web-user-login.error.userNotFoundAfterLogin');
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

                try {
                    $autoLoginToken = $this->autoLoginTokens->issue($userEntity, $ip, $geoIpCountry, $ua->browser());

                    // Cookie musí přežít zavření prohlížeče (to je smysl "zůstat přihlášen") - session
                    // cookie (expire === null) mizí spolu se session, takže by nikdy nic neobnovila.
                    // V cookie je token, v databázi jen jeho hash.
                    $this->getHttpResponse()->setCookie(AutoLoginAuthenticator::COOKIE_NAME, $autoLoginToken, UserAutoLoginEntity::LIFETIME_DAYS . ' days');
                } catch (DbalException $exception) {
                    bdump($exception);
                    $flash = new stdClass();
                    $flash->place = 'general';
                    $flash->type = 'danger';
                    $flash->message = $this->translator->translate('web-user-login.form.submit.autoLoginError');

                    $this->flashMessage($flash);
                    $this->redrawControl('flashes');
                }
            }

            $flash = new stdClass();
            $flash->place = 'general';
            $flash->type = 'success';
            $flash->message = $this->translator->translate('web-user-login.form.submit.success');

            $this->flashMessage($flash);
            $this->redirect(':Web:Home:default');
        } catch (AuthenticationException $exception) {
            $flash = new stdClass();
            $flash->place = 'general';
            $flash->type = 'danger';
            // the message of the exception is a translation key (the authenticators throw keys)
            $flash->message = $this->translator->translate($exception->getMessage());

            $this->flashMessage($flash);
            $this->redrawControl('flashes');
        }
    }

}
