<?php declare(strict_types=1);

namespace App\Core;

use App\Model\Entity\UserEntity;
use DateTimeImmutable;
use Nette\Security\AuthenticationException;
use Nette\Security\Authenticator;
use Nette\Security\IIdentity;
use Nette\Security\Passwords;
use Nette\Security\SimpleIdentity;
use App\Database\EntityManagerDecorator;

class UsernameAndPasswordAuthenticator implements Authenticator
{

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

        if (!$userEntity->isActive) {
            throw new AuthenticationException('User is not active');
        }

        if (!$this->passwords->verify($password, $userEntity->password)) {
            throw new AuthenticationException('Invalid password.');
        }

        $roles = [];

        foreach ($userEntity->roles as $role) {
            $roles[] = $role->name;
        }

        return new SimpleIdentity(
            $userEntity->id,
            $roles,
            [
                'name' => $userEntity->username
            ],
        );
    }

}
