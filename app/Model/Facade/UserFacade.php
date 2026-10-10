<?php

namespace App\Model\Facade;

use App\Database\EntityManagerDecorator;
use App\Forms\Mapper\UserFormMapper;
use App\Model\Entity\MailEntity;
use App\Model\Entity\UserActivationEntity;
use App\Model\Entity\UserEmailEntity;
use App\Model\Entity\UserEntity;
use App\Model\Entity\UserPasswordEntity;
use App\UI\Admin\User\Edit\EditUserValues;
use App\UI\Web\User\Registration\RegistrationValues;
use Contributte\Mailing\IMailBuilderFactory;
use DateTimeImmutable;
use Exception;
use Nette\Localization\Translator;
use Nette\Security\Passwords;
use Nette\Utils\Random;
use Symfony\Contracts\Translation\LocaleAwareInterface;

/**
 * class UserFacade
 *
 * @package App\Model\Facade
 */
class UserFacade
{

    public function __construct(
        private readonly EntityManagerDecorator $em,
        private readonly UserFormMapper         $userFormMapper,
        private readonly Passwords              $passwords,
        private readonly IMailBuilderFactory    $mailBuilderFactory,
        private readonly Translator             $translator,
    )
    {
    }

    public function register(RegistrationValues $values) : void
    {
        $registerFunction = function () use ($values) {
            $userEntity = $this->registerInternal($values);

            $this->sendRegistrationMail($userEntity);
        };

        $this->em->wrapInTransaction($registerFunction);
    }

    private function registerInternal(RegistrationValues $values) : UserEntity
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


    public function update(string $uuid, EditUserValues $values): UserEntity
    {
        return $this->em->wrapInTransaction(
            function() use ($uuid, $values) {
            $userRepository = $this->em->getRepository(UserEntity::class);

            /**
             * @var ?UserEntity $userEntity
             */
            $userEntity = $userRepository
                ->findOneByUuid($uuid);

            if ($userEntity === null) {
                throw new Exception('User not found');
            }

            // known BEFORE the mapper overwrites the e-mail: a changed e-mail is remembered in the e-mail history
            $emailChanged = $userEntity->email !== $values->email;

            $this->userFormMapper->apply($values, $userEntity);

            if ($emailChanged) {
                $userEmailEntity = new UserEmailEntity();
                $userEmailEntity->email = $values->email;
                $userEmailEntity->user = $userEntity;

                $userEntity->addUserEmailEntity($userEmailEntity);
            }

            if ($values->password !== '') {
                // Reuse is already rejected earlier, by PasswordFormControl's own validation rule
                // (setHistoryUser() + its 'admin-user-edit.form.password.alreadyUsed' rule) - the
                // form would not have reached onSuccess otherwise.
                $password = $this->passwords->hash($values->password);

                $userPasswordEntity = new UserPasswordEntity();
                $userPasswordEntity->user = $userEntity;
                $userPasswordEntity->password = $password;

                $userEntity->password = $password;
                $userEntity->addUserPasswordEntity($userPasswordEntity);

                $userEntity->autoLogins->clear();
            }

            if (!$values->isActive) {
                // a deactivated user keeps nothing that logs him in (his session ends at the next request, AutoLoginService)
                $userEntity->autoLogins->clear();
            }

            $userEntity->updatedAt = new DateTimeImmutable();

            $this->em->persist($userEntity);
            $this->em->flush();

            return $userEntity;
        });
    }

    public function activate(string $userId, string $key): void
    {
        $activation = $this->em
            ->getRepository(UserActivationEntity::class)
            ->findOneBy(
                [
                    'activationKey' => $key,
                    'user' => $userId,
                ]
            );

        if ($activation === null || (new DateTimeImmutable()) > $activation->validUntil) {
            throw new Exception('Invalid or expired activation key');
        }

        $user = $activation->user;
        $user->isActive = true;
        $user->updatedAt = new DateTimeImmutable();

        $this->em->remove($activation);
        $this->em->flush();
    }

    private function sendRegistrationMail(UserEntity $userEntity) : void
    {
        $mail = $this->mailBuilderFactory->create();

        $subject = $this->translator->translate('web-user-registration.mail.subject');

        $mail->addTo($userEntity->email, $userEntity->name . ' ' . $userEntity->surname);
        $mail->setSubject($subject);

        if ($this->translator instanceof LocaleAwareInterface) {
            $mail->setTemplateFile(__DIR__ . '/../../UI/Web/User/Registration/Mailing/registration.' . $this->translator->getLocale() . '.latte');
        } else {
            throw new Exception('Unknown translator without locale');
        }

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
        $mailEntity->subject = $subject;
        $mailEntity->body = $mail->getMessage()->getHtmlBody();

        $this->em->persist($mailEntity);
    }

}
