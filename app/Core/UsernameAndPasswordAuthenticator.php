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
        $ip = $this->request->getRemoteAddress();

        if ($this->userLoginAttemptCheckService->isIpBlocked($ip)) {
            throw new AuthenticationException('Z této IP adresy je příliš mnoho pokusů. Zkuste to prosím později.');
        }

        if ($this->userLoginAttemptCheckService->isUserNameBlocked($username)) {
            throw new AuthenticationException('Tento účet je dočasně zablokován. Zkuste to prosím později.');
        }

        /**
         * @var ?UserEntity $userEntity
         */
        $userEntity = $this->em
            ->getRepository(UserEntity::class)
            ->findOneBy(
                [
                    'username' => $username,
                ]
            );

        if (!$userEntity) {
            $this->userLoginAttemptCheckService->logAttempt($username, $ip);

            throw new AuthenticationException('User not found.');
        }

        if (!$userEntity->isActive) {
            $this->userLoginAttemptCheckService->logAttempt($username, $ip);

            throw new AuthenticationException('User is not active');
        }

        if (!$this->passwords->verify($password, $userEntity->password)) {
            $this->userLoginAttemptCheckService->logAttempt($username, $ip);

            throw new AuthenticationException('Invalid password.');
        }

        $roles = [];

        foreach ($userEntity->roles as $role) {
            $roles[] = $role->name;
        }

        $this->userLoginAttemptCheckService->clearAttempts($username, $ip);

        return new SimpleIdentity(
            $userEntity->id,
            $roles,
            [
                'username' => $userEntity->username
            ],
        );
    }

}
