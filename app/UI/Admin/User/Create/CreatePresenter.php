<?php declare(strict_types=1);

namespace App\UI\Admin\User\Create;

use App\Core\AutoLoginAuthenticator;
use App\Forms\EmailFormControlFactory;
use App\Forms\Mapper\UserFormMapper;
use App\Forms\PasswordFormControlFactory;
use App\Forms\UsernameFormControlFactory;
use App\Model\Entity\UserEmailEntity;
use App\Model\Entity\UserPasswordEntity;
use App\UI\Admin\AdminBasePresenter;
use Contributte\FormsBootstrap\BootstrapForm;
use Contributte\FormsBootstrap\Enums\BootstrapVersion;
use Doctrine\DBAL\Exception as DbalException;
use Nette\Application\UI\Form;
use Nette\Localization\Translator;
use Nette\Security\Passwords;
use App\Database\EntityManagerDecorator;
use App\Forms\IconBootstrapForm;
use App\Forms\Renderer\IconBootstrapRenderer;

class CreatePresenter extends AdminBasePresenter
{

    public function __construct(
        private readonly Translator                 $translator,
        private readonly EntityManagerDecorator     $em,
        private readonly Passwords                  $passwords,
        private readonly UserFormMapper             $userFormMapper,
        private readonly PasswordFormControlFactory $passwordFormControlFactory,
        private readonly EmailFormControlFactory    $emailFormControlFactory,
        private readonly UsernameFormControlFactory $usernameFormControlFactory,
    )
    {
        parent::__construct();
    }


    public function createComponentCreateForm() : IconBootstrapForm
    {
        $form = new IconBootstrapForm();
        BootstrapForm::switchBootstrapVersion(BootstrapVersion::V5);

        $form->setTranslator($this->translator);
        $form->setAjax(true);

        $form->addText('name', 'admin-user-edit.form.name.label')
            ->setRequired('admin-user-edit.form.name.required')
            ->setMaxLength(512)
            ->setOption(IconBootstrapRenderer::ICON, 'fas fa-id-card');

        $form->addText('surname', 'admin-user-edit.form.surname.label')
            ->setRequired('admin-user-edit.form.surname.required')
            ->setMaxLength(512)
            ->setOption(IconBootstrapRenderer::ICON, 'fas fa-id-card');

        $form->addComponent(
            $this->usernameFormControlFactory->create((string) $this->translator->translate('admin-user-edit.form.username.label')),
            'username'
        );

        $form->addComponent(
            $this->emailFormControlFactory->create((string) $this->translator->translate('admin-user-edit.form.email.label')),
            'email'
        );

        $passwordControl = $this->passwordFormControlFactory->create((string) $this->translator->translate('admin-user-edit.form.password.label'));
        $form->addComponent($passwordControl, 'password');

        $form->addPassword('password2', 'admin-user-edit.form.password2.label')
            ->setOmitted()
            ->setOption(IconBootstrapRenderer::ICON, 'fas fa-lock')
            ->addConditionOn($passwordControl, Form::Filled, true)
                ->setRequired('admin-user-edit.form.password2.required')
                ->addRule(Form::MinLength, (string) $this->translator->translate('admin-user-edit.form.password2.ruleMinLength', ['minChars' => 8]), 8)
                ->addRule(Form::Equal, 'admin-user-edit.form.password2.ruleEqual', $passwordControl)
            ->endCondition();

        $form->addCheckbox('isActive', 'admin-user-edit.form.isActive.label');

        $form->addSubmit('send', 'admin-user-create.form.submit.label')
            ->setOption(IconBootstrapRenderer::ICON, 'fas fa-plus');

        $form->onSuccess[] = [$this, 'createFormSuccess'];

        return $form;
    }

    public function createFormSuccess(Form $form) : void
    {
        $values = $form->getValues(CreateUserValues::class);
        $userEntity = $this->userFormMapper->apply($values);

        $passwordHash = $this->passwords->hash($values->password);

        $userEntity->password = $passwordHash;

        $userPasswordEntity = new UserPasswordEntity();
        $userPasswordEntity->user = $userEntity;
        $userPasswordEntity->password = $passwordHash;

        $userEntity->addUserPasswordEntity($userPasswordEntity);

        $userEmailEntity = new UserEmailEntity();
        $userEmailEntity->email = $values->email;
        $userEmailEntity->user = $userEntity;

        $userEntity->addUserEmailEntity($userEmailEntity);

        try {
            $this->em->persist($userEntity);
            $this->em->flush();

            $this->flashMessage(
                $this->translator->translate('admin-user-create.form.submit.success', ['username' => $values->username]),
                'success'
            );
            $this->redrawControl('flashes');
        } catch (DbalException $exception) {
            bdump($exception);
            $this->flashMessage($this->translator->translate('messages.operationFailed'), 'danger');
            $this->redrawControl('flashes');
        }
    }

}
