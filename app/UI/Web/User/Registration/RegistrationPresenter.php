<?php declare(strict_types=1);

namespace App\UI\Web\User\Registration;

use App\Model\Entity\MailEntity;
use App\Model\Entity\UserActivationEntity;
use App\Model\Entity\UserEmailEntity;
use App\Model\Entity\UserEntity;
use App\Model\Entity\UserPasswordEntity;
use Contributte\FormsBootstrap\BootstrapForm;
use Contributte\FormsBootstrap\Enums\BootstrapVersion;
use Contributte\Mailing\IMailBuilderFactory;
use Contributte\Translation\Translator;
use Doctrine\DBAL\Exception as DbalException;
use Nette\Application\UI\Form;
use Nette\Application\UI\Presenter;
use Nette\Mail\SmtpException;
use Nette\Security\Passwords;
use Nette\Utils\Random;
use App\Database\EntityManagerDecorator;

class RegistrationPresenter extends Presenter
{
    public function __construct(
        private readonly Translator             $translator,
        private readonly EntityManagerDecorator $em,
        private readonly Passwords              $passwords,
        private readonly IMailBuilderFactory    $mailBuilderFactory,
    )
    {
    }

    public function actionDefault() : void
    {
    }

    public function createComponentRegistrationForm() : BootstrapForm
    {
        $form = new BootstrapForm();
        BootstrapForm::switchBootstrapVersion(BootstrapVersion::V5);

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
            ->setMaxLength(512);

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

        $userEntity->addUserPasswordEntity($userPasswordEntity);

        $userEmailEntity = new UserEmailEntity();
        $userEmailEntity->email = $values->email;
        $userEmailEntity->user = $userEntity;

        $userEntity->addUserEmailEntity($userEmailEntity);

        $userActivationEntity = new UserActivationEntity();
        $userActivationEntity->user = $userEntity;
        $userActivationEntity->activationKey = Random::generate(256);

        $userEntity->addUserActivationEntity($userActivationEntity);

        try {
            $this->em->persist($userEntity);
            $this->em->flush();

            $this->flashMessage(
                $this->translator->translate('web-user-registration.form.submit.success', ['username' => $values->username]),
                'success'
            );
            $this->redrawControl('flashes');
        } catch (DbalException $exception) {
            $this->flashMessage($exception->getMessage(), 'danger');
            $this->redrawControl('flashes');
        }

        $mail = $this->mailBuilderFactory->create();

        $subject = $this->translator->translate('web-user-registration.mail.subject');

        $mail->addTo($values->email, $values->name . ' ' . $values->surname);
        $mail->setSubject($subject);
        $mail->setTemplateFile(__DIR__ . '/Mailing/registration.' . $this->translator->getLocale() . '.latte');
        $mail->setParameters(
            [
                'name' => $values->name,
                'surname' => $values->surname,
                'username' => $values->username,
                'userEntity' => $userEntity,
            ]
        );

        try {
            $mail->send();

            $this->flashMessage(
                $this->translator->translate('web-user-registration.mail.success', ['email' => $values->email]),
                'success'
            );
            $this->redrawControl('flashes');
        } catch (SmtpException $exception) {
            $this->flashMessage($exception->getMessage(), 'danger');
            $this->redrawControl('flashes');
        }

        $mailEntity = new MailEntity();
        $mailEntity->emailTo = $values->email;
        //$mailEntity->userEmail = $userEmailEntity;
        $mailEntity->subject = $subject;
        $mailEntity->body = $mail->getMessage()->getHtmlBody();

        try {
            $this->em->persist($mailEntity);
            $this->em->flush();

            $this->flashMessage(
                $this->translator->translate('web-user-registration.form.submit.success'),
                'success'
            );
            $this->redrawControl('flashes');
        } catch (DbalException $exception) {
            bdump($exception);
            $this->flashMessage($exception->getMessage());
            $this->redrawControl('flashes');
        }
    }

}
