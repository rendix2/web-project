<?php declare(strict_types=1);

namespace Tests\Functional;

use Nette\Http\Session;
use Predis\Client;
use Tests\Support\FunctionalTester;

/**
 * A session in Redis lives as long as `session.expiration`. Without `sessions: {ttl: ...}` in config/ext/redis.neon the
 * handler takes php.ini's session.gc_maxlifetime (24 minutes) and everybody is logged out long before.
 */
class SessionLifetimeCest
{
    private const int EXPIRATION = 14 * 24 * 60 * 60;

    public function theSessionInRedisLivesAsLongAsTheConfiguredExpiration(FunctionalTester $I) : void
    {
        $I->loginAs(FunctionalTester::ROLE_ADMIN);

        $id = $I->grabCookie($I->grabService(Session::class)->getName());
        $I->assertIsString($id);

        // the connection `session` of config/ext/redis.neon: database 1
        $client = new Client('tcp://web-project-redis', ['parameters' => ['database' => 1]]);

        $ttl = $client->ttl($id);
        $I->assertGreaterThan(self::EXPIRATION - 60, $ttl);
        $I->assertLessThanOrEqual(self::EXPIRATION, $ttl);
    }
}
