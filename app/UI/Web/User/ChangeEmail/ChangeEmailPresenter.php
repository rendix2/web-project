<?php declare(strict_types=1);

namespace App\UI\Web\User\ChangeEmail;

use App\Forms\EmailFormControlFactory;
use App\Forms\PasswordFormControlFactory;
use App\Model\Entity\UserEntity;
use Contributte\FormsBootstrap\BootstrapForm;
use Contributte\FormsBootstrap\Enums\BootstrapVersion;
use Nette\Application\UI\Form;
use Nette\Application\UI\Presenter;
use Nette\Http\IResponse;
use Nette\Localization\Translator;
use Nette\Security\Passwords;
use App\Database\EntityManagerDecorator;
use App\Forms\IconBootstrapForm;
use App\Forms\Renderer\IconBootstrapRenderer;
use App\Service\UserEmailChangeService;
use Nette\Application\AbortException;
use Throwable;
use Tracy\Debugger;
use Tracy\ILogger;

/**
 * class ChangeEmailPresenter
 *
 * @package App\UI\Web\User\ChangeEmail
 */
class ChangeEmailPresenter extends Presenter
{

    public function __construct(
        private readonly EntityManagerDecorator     $em,
        private readonly Translator                 $translator,
        private readonly Passwords                  $passwords,
        private readonly UserEmailChangeService     $emailChangeService,
        private readonly PasswordFormControlFactory $passwordFormControlFactory,
        private readonly EmailFormControlFactory    $emailFormControlFactory,
    ) {
        parent::__construct();
    }

    public function actionDefault() : void
    {
        if (!$this->getUser()->isLoggedIn()) {
            $this->error('Pro změnu e-mailu musíte být přihlášeni.', IResponse::S403_Forbidden);
        }

    }

    public function createComponentChangeEmailForm() : IconBootstrapForm
    {
        $form = new IconBootstrapForm();
        BootstrapForm::switchBootstrapVersion(BootstrapVersion::V5);

        $form->setTranslator($this->translator);

        $form->addComponent(
            $this->passwordFormControlFactory->create((string) $this->translator->translate('web-user-changePassword.form.currentPassword.label')), 'currentPassword',
        );

        $emailControl = $this->emailFormControlFactory->create((string) $this->translator->translate('admin-user-edit.form.email.label'));
        $emailControl->setExcludeUserId($this->getUser()->getId());

        $form->addComponent($emailControl, 'email');

        $form->addSubmit('send', 'web-user-changeEmail.form.submit.name')
            ->setOption(IconBootstrapRenderer::ICON, 'fas fa-save');

        $form->onValidate[] = [$this, 'changeEmailFormOnValidate'];
        $form->onSuccess[] = [$this, 'changeEmailFormSuccess'];

        return $form;
    }

    public function changeEmailFormOnValidate(Form $form) : void
    {
        $values = $form->getUntrustedValues(ChangeEmailValues::class);

        $userRepository = $this->em->getRepository(UserEntity::class);

        /**
         * @var ?UserEntity $userEntity
         */
        $userEntity = $userRepository
            ->findOneById((string) $this->getUser()->getId());

        if ($userEntity === null) {
            $this->error('user not found');
        }

        if (!$this->passwords->verify($values->currentPassword, $userEntity->password)) {
            $form->addError($this->translator->translate('web-user-changePassword.form.currentPassword.notMatch'));
        }
    }

    public function changeEmailFormSuccess(Form $form) : void
    {
        $values = $form->getValues(ChangeEmailValues::class);

        $userEntity = $this->em
            ->getRepository(UserEntity::class)
            ->findOneBy(
                [
                    'id' => $this->getUser()->getId(),
                ]
            );

        if ($userEntity === null) {
            $this->error('user not found');
        }

        if (mb_strtolower($values->email) === mb_strtolower($userEntity->email)) {
            $this->flashMessage($this->translator->translate('web-user-changeEmail.form.same'), 'info');
            $this->redrawControl('flashes');

            return;
        }

        try {
            // nothing is changed here: the new address gets a confirmation link, the current one a warning
            $this->emailChangeService->request($userEntity, $values->email);

            $this->flashMessage(
                $this->translator->translate('web-user-changeEmail.form.submit.success'),
                'success'
            );
            $this->redrawControl('flashes');
            $this->redirect('this');
        } catch (AbortException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            bdump($exception);
            Debugger::log($exception, ILogger::EXCEPTION);

            $this->flashMessage($this->translator->translate('web-user-changeEmail.form.submit.error'), 'danger');
            $this->redrawControl('flashes');
        }
    }

}
