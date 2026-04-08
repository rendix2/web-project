<?php declare(strict_types=1);

namespace App\UI\Admin\User\Edit;

use App\Core\AutoLoginAuthenticator;
use App\Forms\EmailFormControlFactory;
use App\Forms\PasswordFormControlFactory;
use App\Forms\UsernameFormControlFactory;
use App\Model\Entity\UserEmailEntity;
use App\Model\Entity\UserEntity;
use App\Model\Entity\UserPasswordEntity;
use Contributte\Datagrid\Datagrid;
use Contributte\FormsBootstrap\BootstrapForm;
use Contributte\FormsBootstrap\Enums\BootstrapVersion;
use Contributte\MenuControl\UI\MenuComponent;
use Contributte\MenuControl\UI\MenuComponentFactory;
use DateTimeImmutable;
use Doctrine\DBAL\Exception as DbalException;
use Nette\Application\UI\Form;
use Nette\Application\UI\Presenter;
use Nette\Http\IResponse;
use Nette\Localization\Translator;
use Nette\Security\AuthenticationException;
use Nette\Security\Passwords;
use App\Database\EntityManagerDecorator;

class EditPresenter extends Presenter
{

    public function __construct(
        private readonly Translator                 $translator,
        private readonly EntityManagerDecorator     $em,
        private readonly Passwords                  $passwords,
        private readonly MenuComponentFactory       $menuFactory,
        private readonly RolesDataGridFactory       $rolesDataGridFactory,
        private readonly EmailsDataGridFactory      $emailsDataGridFactory,
        private readonly AutoLoginAuthenticator     $autoLoginAuthenticator,
        private readonly UsernameFormControlFactory $usernameFormControlFactory,
        private readonly EmailFormControlFactory    $emailFormControlFactory,
        private readonly PasswordFormControlFactory $passwordFormControlFactory,
    )
    {
    }

    public function startup() : void
    {
        parent::startup();

        if (!$this->getUser()->isLoggedIn()) {
            $this->getUser()->setAuthenticator($this->autoLoginAuthenticator);

            $autoLoginCookie = $this->getHttpRequest()->getCookie(AutoLoginAuthenticator::COOKIE_NAME);

            if ($autoLoginCookie === null) {
                $this->error('Not logged in', IResponse::S401_Unauthorized);
            } else {
                try {
                    $this->getUser()->login($autoLoginCookie, null);
                } catch (AuthenticationException $exception) {
                    $this->error('Not logged in', IResponse::S401_Unauthorized);
                }
            }
        }

        if (!$this->getUser()->isInRole('Admin')) {
            $this->error('Forbidden', IResponse::S403_Forbidden);
        }
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
        $userEntity = $this->em
            ->getRepository(UserEntity::class)
            ->findOneBy(
                [
                    'uuid' => $uuid,
                ]
            );

        $this->template->userEntity = $userEntity;
    }

    protected function createComponentRolesGrid() : DataGrid
    {
        return $this->rolesDataGridFactory->create();
    }

    protected function createComponentEmailsGrid() : Datagrid
    {
        return $this->emailsDataGridFactory
            ->setUser($this->getUser())
            ->create();
    }

    protected function createComponentMenu() : MenuComponent
    {
        return $this->menuFactory->create('admin');
    }

    public function createComponentEditForm() : BootstrapForm
    {
        $userEntity = $this->em
            ->getRepository(UserEntity::class)
            ->findOneBy(
                [
                    'uuid' => $this->getParameter('uuid'),
                ]
            );

        if (!$userEntity) {
            $this->error('user not found');
        }

        $form = new BootstrapForm();
        BootstrapForm::switchBootstrapVersion(BootstrapVersion::V5);

        $form->setTranslator($this->translator);
        $form->addProtection('Please try again.');
        $form->setAjax(true);

        $form->addText('name', 'admin-user-edit.form.name.label')
            ->setRequired('admin-user-edit.form.name.required')
            ->setMaxLength(512);

        $form->addText('surname', 'admin-user-edit.form.surname.label')
            ->setRequired('admin-user-edit.form.surname.required')
            ->setMaxLength(512);


        $usernameControl = $this->usernameFormControlFactory->create($this->translator->translate('admin-user-edit.form.username.label'));
        $usernameControl->setExcludeUserId($userEntity->id);
        $form->addComponent($usernameControl, 'username');


        $emailControl = $this->emailFormControlFactory->create($this->translator->translate('admin-user-edit.form.email.label'));
        $emailControl->setExcludeUserId($userEntity->id);
        $form->addComponent($emailControl, 'email');

        $form->addGroup('web-user-changePassword.form.header');

        $passwordControl = $this->passwordFormControlFactory->create($this->translator->translate('admin-user-edit.form.password.label'));
        $passwordControl->setRequired(false);
        $form->addComponent($passwordControl, 'password');

        $form->addPassword('password2', 'admin-user-edit.form.password2.label')
            ->setOmitted()
            ->addConditionOn($form['password'], Form::Filled, true)
                ->setRequired('admin-user-edit.form.password2.required')
                ->addRule(Form::MinLength, $this->translator->translate('admin-user-edit.form.password2.ruleMinLength', ['minChars' => 8]), 8)
                ->addRule(Form::Equal, 'admin-user-edit.form.password2.ruleEqual', $form['password'])
            ->endCondition();

        $form->addCheckbox('isActive', 'admin-user-edit.form.isActive.label');

        $form->addSubmit('send', 'admin-user-edit.form.submit.label');

        $form->onValidate[] = [$this, 'editFormOnValidate'];
        $form->onSuccess[] = [$this, 'editFormSuccess'];

        return $form;
    }

    public function editFormOnValidate(Form $form) : void
    {
        $userEntity = $this->em
            ->getRepository(UserEntity::class)
            ->findOneBy(
                [
                    'uuid' => $this->getParameter('uuid'),
                ]
            );

        if (!$userEntity) {
            $this->error('user not found');
        }

        foreach ($userEntity->passwords as $usedPassword) {
            if ($this->passwords->verify($form->getHttpData()['password'], $usedPassword->password)) {
                $form->addError(
                    $this->translator->translate('admin-user-edit.form.password.alreadyUsed')
                );
            }
        }
    }

    public function editFormSuccess(Form $form) : void
    {
        $userEntity = $this->em
            ->getRepository(UserEntity::class)
            ->findOneBy(
                [
                    'uuid' => $this->getParameter('uuid'),
                ]
            );

        if (!$userEntity) {
            $this->error('user not found');
        }

        $values = $form->getValues();

        if ($userEntity->email !== $form->getHttpData()['email']) {
            $userEmailEntity = new UserEmailEntity();
            $userEmailEntity->email = $values->email;
            $userEmailEntity->user = $userEntity;

            $userEntity->addUserEmailEntity($userEmailEntity);
        }

        $userEntity->name = $values->name;
        $userEntity->surname = $values->surname;
        $userEntity->username = $values->username;
        $userEntity->email = $values->email;
        $userEntity->isActive = (bool) $values->isActive;
        $userEntity->updatedAt = new DateTimeImmutable();

        if ($values->password !== '') {
            $userEntity->password = $this->passwords->hash($values->password);

            $userPasswordEntity = new UserPasswordEntity();
            $userPasswordEntity->user = $userEntity;
            $userPasswordEntity->password = $this->passwords->hash($values->password);

            $userEntity->addUserPasswordEntity($userPasswordEntity);
        }

        try {
            $this->em->persist($userEntity);
            $this->em->flush();

            $this->flashMessage(
                $this->translator->translate('admin-user-edit.form.submit.success', ['username' => $values->username]),
                'success'
            );
            $this->redrawControl('flashes');
            $this->redrawControl('passwordHistory');
            $this['emailsGrid']->reload();
            //$this->redirect('this');
        } catch (DbalException $exception) {
           $this->flashMessage($exception->getMessage(), 'danger');
           $this->redrawControl('flashes');
        }
    }

}
