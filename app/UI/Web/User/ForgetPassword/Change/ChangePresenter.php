<?php

namespace App\UI\Web\User\ForgetPassword\Change;

use App\Model\Entity\MailEntity;
use App\Model\Entity\UserEntity;
use Contributte\FormsBootstrap\BootstrapForm;
use Contributte\FormsBootstrap\Enums\BootstrapVersion;
use Contributte\Mailing\IMailBuilderFactory;
use Doctrine\DBAL\Exception as DbalException;
use Nette\Application\UI\Form;
use Nette\Application\UI\Presenter;
use Nette\Mail\SmtpException;
use App\Database\EntityManagerDecorator;


class ChangePresenter extends Presenter
{

    public function __construct(
        private readonly EntityManagerDecorator $em,
        private readonly \Nette\Localization\Translator             $translator,
        private readonly IMailBuilderFactory    $mailBuilderFactory,
    )
    {
    }

    public function renderDefault(string $userId, string $forgetKey) : void
    {
    }

    public function createComponentSetForm() : BootstrapForm
    {
        $form = new BootstrapForm();
        BootstrapForm::switchBootstrapVersion(BootstrapVersion::V5);

        $form->setTranslator($this->translator);
        $form->addProtection('Please try again.');

        $form->addPassword('password', 'web-user-changePassword.form.newPassword.label')
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

        $form->addSubmit('request', 'web-user-forgetPassword-request.form.submit.label');

        $form->onSuccess[] = [$this, 'setFormSuccess'];

        return $form;
    }

    public function setFormSuccess(Form $form) : void
    {
        $values = $form->getValues();

        $userEntity = $this->em
            ->getRepository(UserEntity::class)
            ->findOneBy(
                [
                    'email' => $values->email,
                ]
            );

        if ($userEntity) {
            $mail = $this->mailBuilderFactory->create();

            $mail->addTo($userEntity->email, $userEntity->name . ' ' . $userEntity->surname);
            $mail->setSubject($this->translator->translate('web-user-forgetPassword-request-subject'));
            $mail->setTemplateFile(__DIR__ . '/Mailing/request.' . $this->translator->getLocale() . '.latte');

            try {
                $mail->send();
            } catch (SmtpException $exception) {
                $this->flashMessage($exception->getMessage());
                $this->redrawControl('flashes');
            }

            $mailEntity = new MailEntity();
            $mailEntity->emailTo = $userEntity->email;
            $mailEntity->body = $mail->getMessage()->getHtmlBody();
            $mailEntity->subject = $this->translator->translate('web-user-forgetPassword-request-subject');

            try {
                $this->em->persist($mailEntity);
                $this->em->flush();
            } catch (DbalException $exception) {
                $this->flashMessage($exception->getMessage(), 'danger');
                $this->redrawControl('flashes');
            }
        }
    }

}
