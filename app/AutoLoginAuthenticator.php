<?php declare(strict_types=1);

namespace App;

use App\Model\Entity\UserAutoLoginEntity;
use Nette\Security\AuthenticationException;
use Nette\Security\Authenticator;
use Nette\Security\IIdentity;
use Nette\Security\SimpleIdentity;
use Nettrine\ORM\EntityManagerDecorator;

class AutoLoginAuthenticator implements Authenticator
{

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