<?php declare(strict_types=1);

namespace App\UI\Web\User\Registration;

use App\Forms\EmailFormControlFactory;
use App\Forms\PasswordFormControlFactory;
use App\Forms\UsernameFormControlFactory;
use App\Model\Facade\UserFacade;
use Contributte\FormsBootstrap\BootstrapForm;
use Contributte\FormsBootstrap\Enums\BootstrapVersion;
use Contributte\Translation\Translator;
use Nette\Application\UI\Form;
use Nette\Application\UI\Presenter;
use Throwable;
use App\Forms\IconBootstrapForm;
use App\Forms\Renderer\IconBootstrapRenderer;

class RegistrationPresenter extends Presenter
{
    public function __construct(
        private readonly Translator                 $translator,
        private readonly PasswordFormControlFactory $passwordFormControlFactory,
        private readonly EmailFormControlFactory    $emailFormControlFactory,
        private readonly UsernameFormControlFactory $usernameFormControlFactory,
        private readonly UserFacade                 $userFacade,
    )
    {
        parent::__construct();
    }

    public function actionDefault() : void
    {
    }

    public function createComponentRegistrationForm() : IconBootstrapForm
    {
        $form = new IconBootstrapForm();
        BootstrapForm::switchBootstrapVersion(BootstrapVersion::V5);

        $form->setTranslator($this->translator);

        $form->addText('name', 'admin-user-edit.form.name.label')
            ->setRequired('admin-user-edit.form.name.required')
            ->setMaxLength(512)
            ->setOption(IconBootstrapRenderer::ICON, 'fas fa-id-card');

        $form->addText('surname', 'admin-user-edit.form.surname.label')
            ->setRequired('admin-user-edit.form.surname.required')
            ->setMaxLength(512)
            ->setOption(IconBootstrapRenderer::ICON, 'fas fa-id-card');

        $form->addComponent(
            $this->usernameFormControlFactory->create($this->translator->translate('admin-user-edit.form.username.label')),
            'username'
        );

        $form->addComponent(
            $this->emailFormControlFactory->create($this->translator->translate('admin-user-edit.form.email.label')),
            'email'
        );

        $passwordControl = $this->passwordFormControlFactory->create($this->translator->translate('web-user-login.form.password.label'));
        $form->addComponent($passwordControl, 'password');

        $form->addPassword('password2', 'admin-user-edit.form.password2.label')
            ->setOmitted()
            ->setOption(IconBootstrapRenderer::ICON, 'fas fa-lock')
            ->setRequired('admin-user-edit.form.password2.required')
            ->addRule(Form::MinLength, $this->translator->translate('admin-user-edit.form.password2.ruleMinLength', ['minChars' => 8]), 8)

            ->addConditionOn($passwordControl, Form::Filled, true)
                ->addRule(Form::Equal, 'admin-user-edit.form.password2.ruleEqual', $passwordControl)
            ->endCondition();

        $form->addSubmit('send', 'web-user-registration.form.submit.label')
            ->setOption(IconBootstrapRenderer::ICON, 'fas fa-user-plus');

        $form->onSuccess[] = [$this, 'registrationFormSuccess'];

        return $form;
    }

    public function registrationFormSuccess(Form $form) : void
    {
        $values = $form->getValues(RegistrationValues::class);

        try {
            $this->userFacade->register($values);

            $this->flashMessage(
                $this->translator->translate('web-user-registration.form.submit.success', ['username' => $values->username]),
                'success'
            );
            $this->redrawControl('flashes');
        } catch (Throwable $exception) {
            bdump($exception);
            $this->flashMessage($this->translator->translate('messages.operationFailed'), 'danger');
            $this->redrawControl('flashes');
        }
    }

}
