<?php declare(strict_types=1);

namespace Tests\Functional;

use App\Database\EntityManagerDecorator;
use App\Model\Entity\UserLoginAttemptEntity;
use App\UI\Web\User\Login\UserLoginAttemptCheckService;
use Tests\Support\FunctionalTester;

/**
 * The limits of failed logins (5 per 15 minutes, per IP and per username). A successful login clears the attempts of THAT USERNAME
 * only: clearing the IP too lets an attacker who owns any account reset the IP limit between his guesses (password spraying).
 * Usernames are compared without regard to case: `Admin` and `admin` are one account, so they are one counter.
 */
class LoginThrottlingCest
{
    private const IP = '10.77.77.77';

    public function _before(FunctionalTester $I) : void
    {
        // logAttempt() reads the user agent of the current request
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36';

        $this->cleanup($I);
    }

    public function _after(FunctionalTester $I) : void
    {
        $this->cleanup($I);
    }

    public function aSuccessfulLoginDoesNotResetTheLimitOfTheIp(FunctionalTester $I) : void
    {
        $service = $this->service($I);

        foreach (['ghost-a', 'ghost-b', 'ghost-c', 'ghost-d'] as $username) {
            $service->logAttempt($username, self::IP);
        }

        $I->assertFalse($service->isIpBlocked(self::IP));

        // the attacker logs in to HIS OWN account
        $service->clearAttempts('throttle-own-account');

        $service->logAttempt('ghost-e', self::IP);

        $I->assertTrue($service->isIpBlocked(self::IP), 'The successful login of another account reset the limit of the IP.');
    }

    public function aSuccessfulLoginClearsTheAttemptsOfTheUsername(FunctionalTester $I) : void
    {
        $service = $this->service($I);

        for ($i = 0; $i < 5; $i++) {
            $service->logAttempt('throttle-victim', self::IP);
        }

        $I->assertTrue($service->isUserNameBlocked('throttle-victim'));

        $service->clearAttempts('throttle-victim');

        $I->assertFalse($service->isUserNameBlocked('throttle-victim'));
    }

    public function theCaseOfTheUsernameDoesNotChangeTheCounter(FunctionalTester $I) : void
    {
        $service = $this->service($I);

        foreach (['throttle-case', 'THROTTLE-CASE', 'Throttle-Case', 'tHROTTLE-cASE', 'throttle-CASE'] as $i => $username) {
            $service->logAttempt($username, '10.77.77.' . (100 + $i));
        }

        $I->assertTrue($service->isUserNameBlocked('Throttle-case'), 'Changing the case of the username avoids its limit.');

        $service->clearAttempts('THROTTLE-case');

        $I->assertFalse($service->isUserNameBlocked('throttle-case'));
    }

    private function service(FunctionalTester $I) : UserLoginAttemptCheckService
    {
        /** @var UserLoginAttemptCheckService $service */
        $service = $I->grabService(UserLoginAttemptCheckService::class);

        return $service;
    }

    private function cleanup(FunctionalTester $I) : void
    {
        /** @var EntityManagerDecorator $em */
        $em = $I->grabService(EntityManagerDecorator::class);
        $em->createQuery('DELETE FROM ' . UserLoginAttemptEntity::class . " a WHERE a.username LIKE 'ghost-%' OR LOWER(a.username) LIKE 'throttle-%'")->execute();
        $em->getConnection()->executeStatement("DELETE FROM user_login_attempt WHERE ip_address <<= '10.77.77.0/24'::inet");
    }

}
