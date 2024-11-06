<?php

namespace App\UI\Web\User\ForgetPassword\Request;

use App\Model\Entity\MailEntity;
use App\Model\Entity\UserEntity;
use App\Model\Entity\UserPasswordRequestEntity;
use Contributte\FormsBootstrap\BootstrapForm;
use Contributte\FormsBootstrap\Enums\BootstrapVersion;
use Contributte\Mailing\IMailBuilderFactory;
use Doctrine\DBAL\Exception as DbalException;
use Nette\Application\UI\Form;
use Nette\Application\UI\Presenter;
use Nette\Mail\SmtpException;
use Nette\Utils\Random;
use Nettrine\ORM\EntityManagerDecorator;
use Symfony\Component\Translation\Translator;

class RequestPresenter extends Presenter
{
    public function __construct(
        private readonly EntityManagerDecorator $em,
        private readonly Translator             $translator,
        private readonly IMailBuilderFactory    $mailBuilderFactory,
    )
    {
    }

    public function renderDefault() : void
    {
    }

    public function createComponentRequestForm() : BootstrapForm
    {
        $form = new BootstrapForm();

        $form->setTranslator($this->translator);
        $form->addProtection('Please try again.');
        BootstrapForm::switchBootstrapVersion(BootstrapVersion::V5);

        $form->addEmail('email', 'admin-user-edit.form.email.label')
            ->setRequired('admin-user-edit.form.email.required')
            ->setMaxLength(1024);

        $form->addSubmit('request', 'web-user-forgetPassword-request.form.submit.label');

        $form->onSuccess[] = [$this, 'requestFormSuccess'];

        return $form;
    }

    public function requestFormSuccess(Form $form) : void
    {
        $values = $form->getValues();

        $userEntity = $this->em
            ->getRepository(UserEntity::class)
            ->findOneBy(
                [
                    'email' => $values->email,
                ]
            );

        if (!$userEntity) {
            $this->flashMessage($this->translator->translate('web-user-forgetPassword-request.form.submit.success'));
            $this->redrawControl('flashes');

            return;
        }

        if ($userEntity->passwordRequests->count()) {
            $this->flashMessage($this->translator->translate('web-user-forgetPassword-request.form.submit.success'));
            $this->redrawControl('flashes');

            return;
        }

        $userPasswordRequestEntity = new UserPasswordRequestEntity();
        $userPasswordRequestEntity->user = $userEntity;
        $userPasswordRequestEntity->forgetKey = Random::generate(256);

        $userEntity->addUserPasswordRequestEntity($userPasswordRequestEntity);

        try {
            $this->em->persist($userEntity);
            $this->em->flush();
        } catch (DbalException $exception) {
            $this->flashMessage($exception->getMessage(), 'danger');
            $this->redrawControl('flashes');
        }

        $mail = $this->mailBuilderFactory->create();

        $mail->addTo($userEntity->email, $userEntity->name . ' ' . $userEntity->surname);
        $mail->setSubject($this->translator->translate('web-user-forgetPassword-request.subject'));
        $mail->setTemplateFile(__DIR__ . '/Mailing/request.' . $this->translator->getLocale() . '.latte');
        $mail->setParameters(
            [
                'userEntity' => $userEntity,
            ]
        );

        try {
            $mail->send();
        } catch (SmtpException $exception) {
            $this->flashMessage($exception->getMessage(), 'danger');
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

        $this->flashMessage($this->translator->translate('web-user-forgetPassword-request.form.submit.success'));
        $this->redrawControl('flashes');
    }

}
