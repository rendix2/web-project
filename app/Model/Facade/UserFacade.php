<?php

namespace App\Model\Facade;

use App\Database\EntityManagerDecorator;
use App\Model\Entity\MailEntity;
use App\Model\Entity\UserActivationEntity;
use App\Model\Entity\UserEmailEntity;
use App\Model\Entity\UserEntity;
use App\Model\Entity\UserPasswordEntity;
use Contributte\Mailing\IMailBuilderFactory;
use Nette\Localization\Translator;
use Nette\Security\Passwords;
use Nette\Utils\ArrayHash;
use Nette\Utils\Random;

/**
 * class UserFacade
 *
 * @package App\Model\Facade
 */
class UserFacade
{

    public function __construct(
        private readonly EntityManagerDecorator $em,
        private readonly Passwords              $passwords,
        private readonly IMailBuilderFactory    $mailBuilderFactory,
        private readonly Translator             $translator,
    )
    {
    }

    public function register(ArrayHash $values) : void
    {
        $registerFunction = function () use ($values) {
            $userEntity = $this->registerInternal($values);

            $this->sendRegistrationMail($userEntity);
        };

        $this->em->wrapInTransaction($registerFunction);
    }

    private function registerInternal(ArrayHash $values) : UserEntity
    {
        $userEntity = new UserEntity();
        $password = $this->passwords->hash($values->password);

        $userEntity->name = $values->name;
        $userEntity->surname = $values->surname;
        $userEntity->username = $values->username;
        $userEntity->email = $values->email;
        $userEntity->password = $password;
        $userEntity->isActive = false;

        $userPasswordEntity = new UserPasswordEntity();
        $userPasswordEntity->user = $userEntity;
        $userPasswordEntity->password = $password;

        $userEntity->addUserPasswordEntity($userPasswordEntity);

        $userEmailEntity = new UserEmailEntity();
        $userEmailEntity->email = $values->email;
        $userEmailEntity->user = $userEntity;

        $userEntity->addUserEmailEntity($userEmailEntity);

        $userActivationEntity = new UserActivationEntity();
        $userActivationEntity->user = $userEntity;
        $userActivationEntity->activationKey = Random::generate(256);

        $userEntity->addUserActivationEntity($userActivationEntity);

        $this->em->persist($userEntity);
        $this->em->flush();

        return $userEntity;
    }

    private function sendRegistrationMail(UserEntity $userEntity) : void
    {
        $mail = $this->mailBuilderFactory->create();

        $subject = $this->translator->translate('web-user-registration.mail.subject');

        $mail->addTo($userEntity->email, $userEntity->name . ' ' . $userEntity->surname);
        $mail->setSubject($subject);
        $mail->setTemplateFile(__DIR__ . '/../../UI/Web/User/Registration/Mailing/registration.' . $this->translator->getLocale() . '.latte');
        $mail->setParameters(
            [
                'name' => $userEntity->name,
                'surname' => $userEntity->surname,
                'username' => $userEntity->username,
                'userEntity' => $userEntity,
            ]
        );

        $mail->send();

        $mailEntity = new MailEntity();
        $mailEntity->emailTo = $userEntity->email;
        //$mailEntity->userEmail = $userEmailEntity;
        $mailEntity->subject = $subject;
        $mailEntity->body = $mail->getMessage()->getHtmlBody();

        $this->em->persist($mailEntity);
    }

}
