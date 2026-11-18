<?php declare(strict_types=1);

namespace Tests\Integration;

use App\Core\UsernameAndPasswordAuthenticator;
use App\Database\EntityManagerDecorator;
use App\Model\Entity\UserEntity;
use App\Model\Entity\UserLoginAttemptEntity;
use Nette\Security\AuthenticationException;
use Nette\Security\Passwords;
use Tests\Support\IntegrationTester;

/**
 * The login does not tell an unknown username from a wrong password (account enumeration, 2026-11-18): both get
 * `web-user-login.error.invalidCredentials`, and an unknown username costs a password hash too (no timing difference).
 * "The account is not active" is said only to somebody who gave the right password of that account.
 * The tests run without a User-Agent header: a failed login of such a request (curl, a bot) used to end in a 500
 * ("parse_user_agent requires a user agent", UserLoginAttemptCheckService::logAttempt()).
 */
class LoginDoesNotRevealAccountsCest
{
    private const string PASSWORD = 'Enumeration-Test-1!';

    private const string INACTIVE = 'enum-inactive';

    public function _before(IntegrationTester $I) : void
    {
        $this->cleanupAttempts($I);

        /** @var Passwords $passwords */
        $passwords = $I->grabService(Passwords::class);

        $user = new UserEntity();
        $user->isActive = false;
        $user->name = 'Enum';
        $user->surname = 'Inactive';
        $user->username = self::INACTIVE;
        $user->email = 'enum-inactive@example.com';
        $user->password = $passwords->hash(self::PASSWORD);

        $em = $this->em($I);
        $em->persist($user);
        $em->flush();
    }

    public function _after(IntegrationTester $I) : void
    {
        $this->cleanupAttempts($I);
    }

    public function anUnknownUsernameAndAWrongPasswordGetTheSameMessage(IntegrationTester $I) : void
    {
        $I->assertSame('web-user-login.error.invalidCredentials', $this->failure($I, 'enum-nobody', self::PASSWORD));
        $I->assertSame('web-user-login.error.invalidCredentials', $this->failure($I, 'TestUsername', 'definitely-wrong'));
    }

    public function anInactiveAccountWithAWrongPasswordLooksLikeAWrongPassword(IntegrationTester $I) : void
    {
        $I->assertSame('web-user-login.error.invalidCredentials', $this->failure($I, self::INACTIVE, 'definitely-wrong'));
    }

    public function anInactiveAccountIsNamedOnlyWithTheRightPassword(IntegrationTester $I) : void
    {
        $I->assertSame('web-user-login.error.userNotActive', $this->failure($I, self::INACTIVE, self::PASSWORD));
    }

    private function failure(IntegrationTester $I, string $username, string $password) : string
    {
        /** @var UsernameAndPasswordAuthenticator $authenticator */
        $authenticator = $I->grabService(UsernameAndPasswordAuthenticator::class);

        try {
            $authenticator->authenticate($username, $password);
        } catch (AuthenticationException $exception) {
            return $exception->getMessage();
        }

        $I->fail('The login of ' . $username . ' succeeded.');
    }

    private function em(IntegrationTester $I) : EntityManagerDecorator
    {
        /** @var EntityManagerDecorator $em */
        $em = $I->grabService(EntityManagerDecorator::class);

        return $em;
    }

    private function cleanupAttempts(IntegrationTester $I) : void
    {
        $em = $this->em($I);
        $em->createQueryBuilder()
            ->delete(UserLoginAttemptEntity::class, '_attempt')
            ->where('LOWER(_attempt.username) IN (:usernames)')
            ->setParameter('usernames', ['enum-nobody', 'testusername', self::INACTIVE])
            ->getQuery()
            ->execute();
    }
}
