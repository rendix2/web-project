<?php declare(strict_types=1);

namespace App\Core;

use App\Model\Entity\UserAutoLoginEntity;
use Nette\Security\AuthenticationException;
use Nette\Security\Authenticator;
use Nette\Security\IIdentity;
use Nette\Security\SimpleIdentity;
use App\Database\EntityManagerDecorator;

class AutoLoginAuthenticator implements Authenticator
{
    public const COOKIE_NAME = 'autoLogin';


    public function __construct(
        private readonly EntityManagerDecorator $em,
    )
    {
    }

    function authenticate(string $user, string $password = null) : IIdentity
    {
        $userAutoLoginEntity = $this->em
            ->getRepository(UserAutoLoginEntity::class)
            ->findOneBy(
                [
                    'token' => $user
                ]
            );

        if (!$userAutoLoginEntity) {
            throw new AuthenticationException('NO autologin found');
        }

        $roles = [];

        foreach ($userAutoLoginEntity->user->roles as $role) {
            $roles[] = $role->name;
        }

        return new SimpleIdentity(
            $userAutoLoginEntity->user->id,
            $roles,
            [
                'name' => $userAutoLoginEntity->user->username
            ],
        );
    }

}
