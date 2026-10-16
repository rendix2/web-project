<?php declare(strict_types=1);

namespace App\Core;

use App\Model\Entity\UserEntity;
use App\UI\Web\User\Login\UserLoginAttemptCheckService;
use DateTimeImmutable;
use Nette\Http\IRequest;
use Nette\Security\AuthenticationException;
use Nette\Security\Authenticator;
use Nette\Security\IIdentity;
use Nette\Security\Passwords;
use Nette\Security\SimpleIdentity;
use App\Database\EntityManagerDecorator;

class UsernameAndPasswordAuthenticator implements Authenticator
{

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
            $this->userLoginAttemptCheckService->logAttempt($username, $ip);

            throw new AuthenticationException('web-user-login.error.userNotFound');
        }

        if (!$userEntity->isActive) {
            $this->userLoginAttemptCheckService->logAttempt($username, $ip);

            throw new AuthenticationException('web-user-login.error.userNotActive');
        }

        if (!$this->passwords->verify($password, $userEntity->password)) {
            $this->userLoginAttemptCheckService->logAttempt($username, $ip);

            throw new AuthenticationException('web-user-login.error.invalidPassword');
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
                'username' => $userEntity->username
            ],
        );
    }

}
