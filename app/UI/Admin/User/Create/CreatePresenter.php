<?php declare(strict_types=1);

namespace App\UI\Admin\User\Create;

use App\Core\AutoLoginAuthenticator;
use App\Forms\EmailFormControlFactory;
use App\Forms\PasswordFormControlFactory;
use App\Forms\UsernameFormControlFactory;
use App\Model\Entity\UserEmailEntity;
use App\Model\Entity\UserEntity;
use App\Model\Entity\UserPasswordEntity;
use Contributte\FormsBootstrap\BootstrapForm;
use Contributte\FormsBootstrap\Enums\BootstrapVersion;
use Contributte\MenuControl\UI\MenuComponent;
use Contributte\MenuControl\UI\MenuComponentFactory;
use Doctrine\DBAL\Exception as DbalException;
use Nette\Application\UI\Form;
use Nette\Application\UI\Presenter;
use Nette\Http\IResponse;
use Nette\Localization\Translator;
use Nette\Security\AuthenticationException;
use Nette\Security\Passwords;
use App\Database\EntityManagerDecorator;

class CreatePresenter extends Presenter
{

    public function __construct(
        private readonly Translator                 $translator,
        private readonly EntityManagerDecorator     $em,
        private readonly Passwords                  $passwords,
        private readonly MenuComponentFactory       $menuFactory,
        private readonly AutoLoginAuthenticator     $autoLoginAuthenticator,
        private readonly PasswordFormControlFactory $passwordFormControlFactory,
        private readonly EmailFormControlFactory    $emailFormControlFactory,
        private readonly UsernameFormControlFactory $usernameFormControlFactory,
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

    protected function createComponentMenu() : MenuComponent
    {
        return $this->menuFactory->create('admin');
    }

    public function createComponentCreateForm() : BootstrapForm
    {
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

        $form->addComponent(
            $this->usernameFormControlFactory->create($this->translator->translate('admin-user-edit.form.username.label')),
            'username'
        );

        $form->addComponent(
            $this->emailFormControlFactory->create($this->translator->translate('admin-user-edit.form.email.label')),
            'email'
        );

        $form->addComponent(
            $this->passwordFormControlFactory->create($this->translator->translate('admin-user-edit.form.password.label')),
            'password'
        );

        $form->addPassword('password2', 'admin-user-edit.form.password2.label')
            ->setOmitted()
            ->addConditionOn($form['password'], Form::Filled, true)
            ->setRequired('admin-user-edit.form.password2.required')
            ->addRule(Form::MinLength, $this->translator->translate('admin-user-edit.form.password2.ruleMinLength', ['minChars' => 8]), 8)
            ->addRule(Form::Equal, 'admin-user-edit.form.password2.ruleEqual', $form['password'])
            ->endCondition();

        $form->addCheckbox('isActive', 'admin-user-edit.form.isActive.label');

        $form->addSubmit('send', 'admin-user-create.form.submit.label');

        $form->onSuccess[] = [$this, 'createFormSuccess'];

        return $form;
    }

    public function createFormSuccess(Form $form) : void
    {
        $values = $form->getValues();
        $userEntity = new UserEntity();

        $userEntity->name = $values->name;
        $userEntity->surname = $values->surname;
        $userEntity->username = $values->username;
        $userEntity->email = $values->email;
        $userEntity->password = $this->passwords->hash($values->password);
        $userEntity->isActive = (bool) $values->isActive;

        $userPasswordEntity = new UserPasswordEntity();
        $userPasswordEntity->user = $userEntity;
        $userPasswordEntity->password = $this->passwords->hash($values->password);

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
            //$this->redirect('this');
        } catch (DbalException $exception) {
            $this->flashMessage($exception->getMessage(), 'danger');
            $this->redrawControl('flashes');
        }
    }

}
