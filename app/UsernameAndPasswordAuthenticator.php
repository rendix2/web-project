<?php declare(strict_types=1);

namespace App;

use App\Model\Entity\UserEntity;
use Nette\Security\AuthenticationException;
use Nette\Security\Authenticator;
use Nette\Security\IIdentity;
use Nette\Security\Passwords;
use Nette\Security\SimpleIdentity;
use Nettrine\ORM\EntityManagerDecorator;

class UsernameAndPasswordAuthenticator implements Authenticator
{
    public const MAX_LOGIN_COUNT = 5;

    public function __construct(
        private readonly EntityManagerDecorator $em,
        private readonly Passwords              $passwords,
    )
    {
    }

    public function authenticate(string $user, string $password) : IIdentity
    {
        $userEntity = $this->em
            ->getRepository(UserEntity::class)
            ->findOneBy(
                [
                    'username' => $user,
                ]
            );

        if (!$userEntity) {
            throw new AuthenticationException('User not found.');
        }

        if (
            $userEntity->lastLoginAt &&
            (new \DateTimeImmutable())->diff($userEntity->lastLoginAt)->i > 30
        ) {
            $userEntity->lastLoginCount = 0;
        }

        $userEntity->lastLoginCount++;
        $userEntity->lastLoginAt = new \DateTimeImmutable();

        $this->em->persist($userEntity);
        $this->em->flush();

        if (
            $userEntity->lastLoginCount >= static::MAX_LOGIN_COUNT &&
            $userEntity->lastLoginAt && (new \DateTimeImmutable())->diff($userEntity->lastLoginAt)->i < 30
        ) {
            throw new AuthenticationException('There was so much tries. Try again later please.');
        }

        if (!$userEntity->isActive) {
            throw new AuthenticationException('User is not active');
        }

        if (!$this->passwords->verify($password, $userEntity->password)) {
            throw new AuthenticationException('Invalid password.');
        }

        return new SimpleIdentity(
            $userEntity->id,
            'user',
            [
                'name' => $userEntity->username
            ],
        );
    }

}
