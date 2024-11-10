<?php declare(strict_types=1);

namespace App\UI\Admin\User\Create;

use App\Core\AutoLoginAuthenticator;
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
use Nettrine\ORM\EntityManagerDecorator;

class CreatePresenter extends Presenter
{

    public function __construct(
        private readonly Translator             $translator,
        private readonly EntityManagerDecorator $em,
        private readonly Passwords              $passwords,
        private readonly MenuComponentFactory   $menuFactory,
        private readonly AutoLoginAuthenticator $autoLoginAuthenticator,
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

        $form->setTranslator($this->translator);
        $form->addProtection('Please try again.');
        $form->setAjax(true);
        BootstrapForm::switchBootstrapVersion(BootstrapVersion::V5);

        $form->addText('name', 'admin-user-edit.form.name.label')
            ->setRequired('admin-user-edit.form.name.required')
            ->setMaxLength(512);

        $form->addText('surname', 'admin-user-edit.form.surname.label')
            ->setRequired('admin-user-edit.form.surname.required')
            ->setMaxLength(512);

        $form->addText('username', 'admin-user-edit.form.username.label')
            ->setRequired('admin-user-edit.form.username.required')
            ->setMaxLength(512);

        $form->addEmail('email', 'admin-user-edit.form.email.label')
            ->setRequired('admin-user-edit.form.email.required')
            ->setMaxLength(1024);

        $form->addPassword('password', 'admin-user-edit.form.password.label')
            ->setRequired('admin-user-edit.form.password.required')
            ->addRule(Form::MinLength, $this->translator->translate('admin-user-edit.form.password.ruleMinLength', ['minChars' => 8]), 8)
            ->addCondition(Form::MinLength, 8)
            ->addRule(Form::Pattern, 'admin-user-edit.form.password.ruleAtLeastNumber', '.*[0-9].*')
            //->addRule(Form::Pattern, 'registration.form.password.ruleNotStartNumber', '^[^0-9].*')
            //->addRule(Form::Pattern, 'registration.form.password.ruleNotFinishNumber', '.*[^0-9]$')
            ->addRule(Form::Pattern, 'admin-user-edit.form.password.ruleAtLeastLowerChar', '.*[a-z].*')
            ->addRule(Form::Pattern, 'admin-user-edit.form.password.ruleAtLeastUpperChar', '.*[A-Z].*')
            //->addRule(Form::Pattern, 'registration.form.password.ruleNotStartUpperChar', '^[^A-Z].*')
            //->addRule(Form::Pattern, 'registration.form.password.ruleNotFinishUpperChar', '.*[^A-Z]$')
            ->endCondition();

        $form->addPassword('password2', 'admin-user-edit.form.password2.label')
            ->setOmitted()
            ->addConditionOn($form['password'], Form::Filled, true)
            ->setRequired('admin-user-edit.form.password2.required')
            ->addRule(Form::MinLength, $this->translator->translate('admin-user-edit.form.password2.ruleMinLength', ['minChars' => 8]), 8)
            ->addRule(Form::Equal, 'admin-user-edit.form.password2.ruleEqual', $form['password'])
            ->endCondition();

        $form->addCheckbox('isActive', 'admin-user-edit.form.isActive.label');

        $form->addSubmit('send', 'admin-user-create.form.submit.label');

        $form->onValidate[] = [$this, 'createFormOnValidate'];
        $form->onSuccess[] = [$this, 'createFormSuccess'];

        return $form;
    }

    public function createFormOnValidate(Form $form) : void
    {
        $usernameExists = $this->em
            ->getRepository(UserEntity::class)
            ->findOneBy(
                [
                    'username' => $form->getHttpData()['username']
                ]
            );

        if ($usernameExists) {
            $form->addError(
                $this->translator->translate('admin-user-edit.form.username.exists', ['username' => $form->getHttpData()['username']])
            );
        }

        $emailExists = $this->em
            ->getRepository(UserEntity::class)
            ->findOneBy(
                [
                    'email' => $form->getHttpData()['email']
                ]
            );

        if ($emailExists) {
            $form->addError(
                $this->translator->translate('admin-user-edit.form.email.exists', ['email' => $form->getHttpData()['email']])
            );
        }

        $historyEmailExists = $this->em
            ->getRepository(UserEmailEntity::class)
            ->findOneBy(
                [
                    'email' => $form->getHttpData()['email']
                ]
            );

        if ($historyEmailExists) {
            $form->addError(
                $this->translator->translate('admin-user-edit.form.email.exists', ['email' => $form->getHttpData()['email']])
            );
        }
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
