<?php

namespace App\Model\Facade;

use App\Database\EntityManagerDecorator;
use App\Model\Entity\MailEntity;
use App\Model\Entity\UserActivationEntity;
use App\Model\Entity\UserAutoLoginEntity;
use App\Model\Entity\UserEmailEntity;
use App\Model\Entity\UserEntity;
use App\Model\Entity\UserPasswordEntity;
use App\Model\Entity\UserPasswordRequestEntity;
use App\Model\Repository\UserRepository;
use Contributte\Mailing\IMailBuilderFactory;
use DateTimeImmutable;
use Exception;
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


    public function update(string $uuid, ArrayHash $values): UserEntity
    {
        return $this->em->wrapInTransaction(
            function() use ($uuid, $values) {
            /**
             * @var UserRepository $userRepository
             */
            $userRepository = $this
                ->em
                ->getRepository(UserEntity::class);

            /**
             * @var ?UserEntity $userEntity
             */
            $userEntity = $userRepository
                ->findOneByUuid($uuid);

            if (!$userEntity) {
                throw new \Exception('User not found');
            }

            if (isset($values->fullName) && str_contains($values->fullName, ' ')) {
                [$userEntity->name, $userEntity->surname] = explode(' ', $values->fullName, 2);
            } else {
                if (isset($values->name)) {
                    $userEntity->name = $values->name;
                }
                if (isset($values->surname)) {
                    $userEntity->surname = $values->surname;
                }
            }

            if (isset($values->username)) {
                $userEntity->username = $values->username;
            }

            if (isset($values->email) && $userEntity->email !== $values->email) {
                $userEmailEntity = new UserEmailEntity();
                $userEmailEntity->email = $values->email;
                $userEmailEntity->user = $userEntity;

                $userEntity->addUserEmailEntity($userEmailEntity);
                $userEntity->email = $values->email;
            }

            if (isset($values->password) && $values->password !== '') {
                /*
                foreach ($userEntity->passwords as $usedPassword) {
                    if ($this->passwords->verify($values->password, $usedPassword->password)) {
                        throw new Exception('admin-user-edit.form.password.alreadyUsed');
                    }
                }
                */

                $password = $this->passwords->hash($values->password);

                $userPasswordEntity = new UserPasswordEntity();
                $userPasswordEntity->user = $userEntity;
                $userPasswordEntity->password = $password;

                $userEntity->password = $password;
                $userEntity->addUserPasswordEntity($userPasswordEntity);

                $userEntity->autoLogins->clear();

                /*
                $tokens = $this->em
                    ->getRepository(UserAutoLoginEntity::class)
                    ->findBy(
                        [
                            'user' => $userEntity
                        ]
                    );

                foreach ($tokens as $token) {
                    $this->em->remove($token);
                }
                */
            }

            if (isset($values->isActive)) {
                $userEntity->isActive = (bool) $values->isActive;
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

        if (!$activation || (new DateTimeImmutable()) > $activation->validUntil) {
            throw new Exception('Invalid or expired activation key');
        }

        $user = $activation->user;
        $user->isActive = true;
        $user->updatedAt = new DateTimeImmutable();

        $this->em->remove($activation);
        $this->em->flush();
    }

    public function changeEmail(string $userId, string $newEmail): void
    {
        $user = $this->getUser($userId);
        $user->email = $newEmail;
        $user->updatedAt = new DateTimeImmutable();

        $this->addEmailHistory($user, $newEmail);

        $this->em->flush();
    }

    public function changePassword(string $userId, string $newPassword): void
    {
        $user = $this->getUser($userId);
        $hashed = $this->passwords->hash($newPassword);

        $user->password = $hashed;
        $user->updatedAt = new DateTimeImmutable();

        $this->addPasswordHistory($user, $hashed);
        $this->invalidateAutologinTokens($user);

        $this->em->flush();
    }

    public function createPasswordRequest(string $email): void
    {
        $user = $this->em
            ->getRepository(UserEntity::class)
            ->findOneBy(
                [
                    'email' => $email
                ]
            );

        if (!$user) {
            throw new Exception('User not found');
        }

        $request = new UserPasswordRequestEntity();
        $request->user = $user;
        $request->forgetKey = Random::generate(256);

        $user->addUserPasswordRequestEntity($request);
        $this->em->persist($request);
        $this->em->flush();

        $this->sendPasswordResetMail($user);
    }

    private function getUser(string $id): UserEntity
    {
        $user = $this->em->getRepository(UserEntity::class)->find($id);

        if (!$user) {
            throw new Exception('User not found');
        }

        return $user;
    }

    private function addPasswordHistory(UserEntity $user, string $hashedPassword): void
    {
        $history = new UserPasswordEntity();
        $history->user = $user;
        $history->password = $hashedPassword;
        $user->addUserPasswordEntity($history);
        $this->em->persist($history);
    }

    private function addEmailHistory(UserEntity $user, string $email): void
    {
        $history = new UserEmailEntity();
        $history->user = $user;
        $history->email = $email;
        $user->addUserEmailEntity($history);
        $this->em->persist($history);
    }

    private function invalidateAutologinTokens(UserEntity $user): void
    {
        $tokens = $this->em
            ->getRepository(UserAutoLoginEntity::class)
            ->findBy(
                [
                    'user' => $user
                ]
            );

        foreach ($tokens as $token) {
            $this->em->remove($token);
        }
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

    private function sendPasswordResetMail(UserEntity $user): void
    {

    }

}
