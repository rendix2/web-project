<?php declare(strict_types=1);

namespace App\Core;

use Nette\Security\AuthenticationException;
use Nette\Security\Authenticator;
use Nette\Security\IIdentity;
use Nette\Security\SimpleIdentity;

/**
 * Logs in by the token of the "stay logged in" cookie (the "username" argument is the token, there is no password).
 * Only an active user with a valid (existing, not expired) token gets in.
 */
class AutoLoginAuthenticator implements Authenticator
{
    public const COOKIE_NAME = 'autoLogin';

    public function __construct(
        private readonly AutoLoginTokenService $tokens,
    )
    {
    }

    public function authenticate(string $username, string $password) : IIdentity
    {
        $entity = $this->tokens->find($username);

        if ($entity === null) {
            throw new AuthenticationException('No valid autologin token.');
        }

        $user = $entity->user;

        if (!$user->isActive) {
            // a deactivated user keeps nothing that logs him in
            $this->tokens->revokeAll($user);

            throw new AuthenticationException('User is not active.');
        }

        $roles = [];

        foreach ($user->roles as $role) {
            $roles[] = $role->name;
        }

        return new SimpleIdentity(
            $user->id,
            $roles,
            [
                'username' => $user->username,
            ],
        );
    }

}
