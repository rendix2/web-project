<?php declare(strict_types=1);

namespace App\UI\Admin\User\Edit;

use App\Forms\EmailFormControlFactory;
use App\Forms\PasswordFormControlFactory;
use App\Forms\UsernameFormControlFactory;
use App\Model\Entity\UserEntity;
use App\Model\Facade\UserFacade;
use App\UI\Admin\AdminBasePresenter;
use Contributte\Datagrid\Datagrid;
use Contributte\FormsBootstrap\BootstrapForm;
use Contributte\FormsBootstrap\Enums\BootstrapVersion;
use Contributte\MenuControl\UI\MenuComponent;
use Contributte\MenuControl\UI\MenuComponentFactory;
use Nette\Application\UI\Form;
use Nette\Http\IResponse;
use Nette\Localization\Translator;
use App\Database\EntityManagerDecorator;
use stdClass;
use Throwable;

class EditPresenter extends AdminBasePresenter
{
    private UserEntity $userEntity;

    public function __construct(
        private readonly Translator                 $translator,
        private readonly EntityManagerDecorator     $em,
        private readonly MenuComponentFactory       $menuFactory,
        private readonly RoleDataGrid               $roleDataGrid,
        private readonly EmailDataGrid              $emailDataGrid,
        private readonly PasswordHistoryGrid        $passwordHistoryGrid,
        private readonly UsernameFormControlFactory $usernameFormControlFactory,
        private readonly EmailFormControlFactory    $emailFormControlFactory,
        private readonly PasswordFormControlFactory $passwordFormControlFactory,
        private readonly UserFacade                 $userFacade,
    )
    {
        parent::__construct();
    }

    public function actionDefault(string $uuid) : void
    {
        $userEntity = $this->em
            ->getRepository(UserEntity::class)
            ->findOneBy(
                [
                    'uuid' => $uuid,
                ]
            );

        if ($userEntity === null) {
            $this->error('User not found', IResponse::S404_NotFound);
        }

        $this->userEntity = $userEntity;

        $this['editForm']->setDefaults(
            [
                'name' => $userEntity->name,
                'surname' => $userEntity->surname,
                'username' => $userEntity->username,
                'email' => $userEntity->email,
                'isActive' => $userEntity->isActive,
            ]
        );
    }

    public function renderDefault(string $uuid) : void
    {
        $this->getTemplate()->userEntity = $this->userEntity;
    }

    protected function createComponentRolesGrid() : Datagrid
    {
        return $this->roleDataGrid
            ->setUserEntity($this->userEntity)
            ->create();
    }

    protected function createComponentEmailsGrid() : Datagrid
    {
        return $this->emailDataGrid
            ->setUserEntity($this->userEntity)
            ->create();
    }

    protected function createComponentPasswordHistoryGrid() : Datagrid
    {
        return $this->passwordHistoryGrid
            ->setUserEntity($this->userEntity)
            ->create();
    }

    protected function createComponentMenu() : MenuComponent
    {
        return $this->menuFactory->create('admin');
    }

    public function createComponentEditForm() : BootstrapForm
    {
        $form = new BootstrapForm();
        BootstrapForm::switchBootstrapVersion(BootstrapVersion::V5);

        $form->setTranslator($this->translator);
        $form->setAjax(true);

        $form->addText('name', 'admin-user-edit.form.name.label')
            ->setRequired('admin-user-edit.form.name.required')
            ->setMaxLength(512);

        $form->addText('surname', 'admin-user-edit.form.surname.label')
            ->setRequired('admin-user-edit.form.surname.required')
            ->setMaxLength(512);

        $usernameControl = $this->usernameFormControlFactory->create((string) $this->translator->translate('admin-user-edit.form.username.label'));
        $usernameControl->setExcludeUserId($this->userEntity->id);

        $form->addComponent($usernameControl, 'username');

        $emailControl = $this->emailFormControlFactory->create((string) $this->translator->translate('admin-user-edit.form.email.label'));
        $emailControl->setExcludeUserId($this->userEntity->id);

        $form->addComponent($emailControl, 'email');

        $form->addGroup('web-user-changePassword.form.header');

        $passwordControl = $this->passwordFormControlFactory->create((string) $this->translator->translate('admin-user-edit.form.password.label'));
        $passwordControl->setRequired(false);
        $passwordControl->setHistoryUser($this->userEntity);

        $form->addComponent($passwordControl, 'password');

        $form->addPassword('password2', 'admin-user-edit.form.password2.label')
            ->setOmitted()
            ->addConditionOn($passwordControl, Form::Filled, true)
                ->setRequired('admin-user-edit.form.password2.required')
                ->addRule(Form::MinLength, (string) $this->translator->translate('admin-user-edit.form.password2.ruleMinLength', ['minChars' => 8]), 8)
                ->addRule(Form::Equal, 'admin-user-edit.form.password2.ruleEqual', $passwordControl)
            ->endCondition();

        $form->addCheckbox('isActive', 'admin-user-edit.form.isActive.label');

        $form->addSubmit('send', 'admin-user-edit.form.submit.label');

        $form->onSuccess[] = [$this, 'editFormSuccess'];

        return $form;
    }

    public function editFormSuccess(Form $form) : void
    {
        $values = $form->getValues(EditUserValues::class);
        $uuid = $this->getParameter('uuid');

        if (!is_string($uuid)) {
            $this->error('User not found');
        }

        try {
            $this->userFacade->update($uuid, $values);

            $flash = new stdClass();
            $flash->place = 'general';
            $flash->type = 'success';
            $flash->message = $this->translator->translate('admin-user-edit.form.submit.success', ['username' => $values->username]);

            $this->flashMessage($flash);
            $this->redrawControl('flashes');
            $this->redrawControl('editFormWrapper');
            $this->redrawControl('editForm');
            $this['emailsGrid']->reload();
            $this['passwordHistoryGrid']->reload();
            $this['rolesGrid']->reload();
        } catch (Throwable $exception) {
            bdump($exception);

            $flash = new stdClass();
            $flash->place = 'general';
            $flash->type = 'danger';
            $flash->message = $this->translator->translate('messages.operationFailed');

            $this->flashMessage($flash);
            $this->redrawControl('flashes');
        }
    }

}
