<?php declare(strict_types=1);

namespace App\Core;

use App\Database\EntityManagerDecorator;
use App\Model\Entity\UserEntity;
use App\UI\Web\User\Login\UserLoginAttemptCheckService;
use Nette\Http\IRequest;
use Nette\Security\AuthenticationException;
use Nette\Security\Authenticator;
use Nette\Security\IIdentity;
use Nette\Security\Passwords;
use Nette\Security\SimpleIdentity;

/**
 * The login by username and password. It does not reveal which accounts exist (account enumeration): an unknown username and
 * a wrong password get the same message (INVALID_CREDENTIALS), and an unknown username costs a password hash too, so it does
 * not answer faster. "The account is not active" is said only after the right password of that account.
 */
class UsernameAndPasswordAuthenticator implements Authenticator
{
    public const string INVALID_CREDENTIALS = 'web-user-login.error.invalidCredentials';

    public const string NOT_ACTIVE = 'web-user-login.error.userNotActive';

    public function __construct(
        private readonly EntityManagerDecorator       $em,
        private readonly Passwords                    $passwords,
        private readonly UserLoginAttemptCheckService $userLoginAttemptCheckService,
        private readonly IRequest                     $request,
    )
    {
    }

    public function authenticate(string $username, string $password) : IIdentity
    {
        $ip = $this->request->getRemoteAddress() ?? '0.0.0.0';

        if ($this->userLoginAttemptCheckService->isIpBlocked($ip)) {
            throw new AuthenticationException('web-user-login.error.ipBlocked');
        }

        if ($this->userLoginAttemptCheckService->isUserNameBlocked($username)) {
            throw new AuthenticationException('web-user-login.error.userBlocked');
        }

        $userEntity = $this->em->getRepository(UserEntity::class)->findOneByUsername($username);

        if ($userEntity === null) {
            // the same work as the check of a real password: an unknown username must not answer faster
            $this->passwords->hash($password);
            $this->userLoginAttemptCheckService->logAttempt($username, $ip);

            throw new AuthenticationException(self::INVALID_CREDENTIALS);
        }

        if (!$this->passwords->verify($password, $userEntity->password)) {
            $this->userLoginAttemptCheckService->logAttempt($username, $ip);

            throw new AuthenticationException(self::INVALID_CREDENTIALS);
        }

        if (!$userEntity->isActive) {
            // the visitor knows the password: telling him why he cannot log in reveals nothing
            throw new AuthenticationException(self::NOT_ACTIVE);
        }

        $roles = [];

        foreach ($userEntity->roles as $role) {
            $roles[] = $role->name;
        }

        $this->userLoginAttemptCheckService->clearAttempts($username);

        return new SimpleIdentity(
            $userEntity->id,
            $roles,
            [
                'username' => $userEntity->username,
            ],
        );
    }
}
