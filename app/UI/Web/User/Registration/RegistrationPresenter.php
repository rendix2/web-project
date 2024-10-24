<?php declare(strict_types=1);

namespace App\UI\Web\User\Registration;

use App\Model\Entity\UserEntity;
use App\Model\Entity\UserPasswordEntity;
use Contributte\FormsBootstrap\BootstrapForm;
use Doctrine\DBAL\Exception;
use Nette\Application\UI\Form;
use Nette\Application\UI\Presenter;
use Nette\Localization\Translator;
use Nette\Security\Passwords;
use Nettrine\ORM\EntityManagerDecorator;

class RegistrationPresenter extends Presenter
{
    public function __construct(
        private readonly Translator             $translator,
        private readonly EntityManagerDecorator $em,
        private readonly Passwords              $passwords,
    )
    {
    }

    public function createComponentRegistrationForm() : BootstrapForm
    {
        $form = new BootstrapForm();

        $form->setTranslator($this->translator);
        $form->addProtection('Please try again.');

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
                //->addRule(Form::Pattern, 'admin-user-edit.form.password.ruleNotStartNumber', '^[^0-9].*')
                //->addRule(Form::Pattern, 'admin-user-edit.form.password.ruleNotFinishNumber', '.*[^0-9]$')
                ->addRule(Form::Pattern, 'admin-user-edit.form.password.ruleAtLeastLowerChar', '.*[a-z].*')
                ->addRule(Form::Pattern, 'admin-user-edit.form.password.ruleAtLeastUpperChar', '.*[A-Z].*')
                //->addRule(Form::Pattern, 'admin-user-edit.form.password.ruleNotStartUpperChar', '^[^A-Z].*')
                //->addRule(Form::Pattern, 'admin-user-edit.form.password.ruleNotFinishUpperChar', '.*[^A-Z]$')
            ->endCondition();

        $form->addPassword('password2', 'admin-user-edit.form.password2.label')
            ->setOmitted()
            ->setRequired('admin-user-edit.form.password2.required')
            ->addRule(Form::MinLength, $this->translator->translate('admin-user-edit.form.password2.ruleMinLength', ['minChars' => 8]), 8)

            ->addConditionOn($form['password'], Form::Filled, true)
                ->addRule(Form::Equal, 'admin-user-edit.form.password2.ruleEqual', $form['password'])
            ->endCondition();

        $form->addSubmit('send', 'web-user-registration.form.submit.label');

        $form->onValidate[] = [$this, 'registrationFormOnValidate'];
        $form->onSuccess[] = [$this, 'registrationFormSuccess'];

        return $form;
    }

    public function registrationFormOnValidate(Form $form) : void
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
    }

    public function registrationFormSuccess(Form $form) : void
    {
        $values = $form->getValues();

        $userEntity = new UserEntity();

        $userEntity->name = $values->name;
        $userEntity->surname = $values->surname;
        $userEntity->username = $values->username;
        $userEntity->email = $values->email;
        $userEntity->password = $this->passwords->hash($values->password);
        $userEntity->isActive = false;

        $userPasswordEntity = new UserPasswordEntity();
        $userPasswordEntity->user = $userEntity;
        $userPasswordEntity->password = $this->passwords->hash($values->password);

        $userEntity->addUserPassword($userPasswordEntity);

        try {
            $this->em->persist($userEntity);
            $this->em->flush();

            $this->flashMessage(
                $this->translator->translate('web-user-registration.form.submit.success', ['username' => $values->username]),
                'success'
            );
            $this->redrawControl('flashes');
        } catch (Exception $exception) {
            $form->addError($exception->getMessage());
        }
    }

}
