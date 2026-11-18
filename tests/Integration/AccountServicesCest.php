<?php declare(strict_types=1);

namespace Tests\Integration;

use App\Core\UsernameAndPasswordAuthenticator;
use App\Database\EntityManagerDecorator;
use App\Model\Entity\GeoIpCacheEntity;
use App\Model\Entity\UserEntity;
use App\Service\GeoIpService;
use App\UI\Web\User\ForgetPassword\Request\UserPasswordRequestAttemptCheckService;
use App\UI\Web\User\Login\UserLoginAttemptCheckService;
use Contributte\Guzzlette\ClientFactory;
use Contributte\Guzzlette\SnapshotStack;
use DateTimeImmutable;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use InvalidArgumentException;
use Nette\Security\AuthenticationException;
use Tests\Support\IntegrationTester;

/**
 * The services behind the account pages: the repository of the users (case-insensitive lookups), the throttling of the password
 * requests and of the logins, and the GeoIP lookup with its cache (the provider is a Guzzle MockHandler, no network).
 */
class AccountServicesCest
{
    public function theRepositoryFindsUsersByIdUuidAndCaseInsensitively(IntegrationTester $I) : void
    {
        $user = $I->createUser('inrepo');
        $repository = $I->em()->getRepository(UserEntity::class);

        $I->assertSame($user->id, $repository->findOneById((string) $user->id)?->id);
        $I->assertSame($user->id, $repository->findOneByUuid((string) $user->uuid)?->id);
        $I->assertSame($user->id, $repository->findOneByEmail('  ' . strtoupper($user->email) . ' ')?->id);
        $I->assertSame($user->id, $repository->findOneByUsername(strtoupper($user->username))?->id);
        $I->assertNull($repository->findOneByEmail('nobody-' . $user->email));
    }

    public function thePasswordRequestsOfAnAddressAndOfAnIpAreLimited(IntegrationTester $I) : void
    {
        $service = $I->grabService(UserPasswordRequestAttemptCheckService::class);
        $email = 'InThrottle' . random_int(100000, 999999) . '@example.com';
        $ip = '198.18.' . random_int(0, 255) . '.' . random_int(1, 254);

        $I->assertFalse($service->isEmailBlocked($email));
        $I->assertFalse($service->isIpBlocked($ip));

        for ($i = 0; $i < 5; $i++) {
            $service->logAttempt($email, $ip);
        }

        $I->assertTrue($service->isEmailBlocked(strtolower($email)));
        $I->assertTrue($service->isIpBlocked($ip));

        $I->expectThrowable(InvalidArgumentException::class, static fn () => $service->logAttempt($email, 'not-an-ip'));
        $I->expectThrowable(InvalidArgumentException::class, static fn () => $service->isIpBlocked('not-an-ip'));
    }

    public function theLoginOfBlockedUsernameIsRefusedBeforeThePasswordIsChecked(IntegrationTester $I) : void
    {
        $user = $I->createUser('inblocked');
        $attempts = $I->grabService(UserLoginAttemptCheckService::class);

        for ($i = 0; $i < 5; $i++) {
            $attempts->logAttempt(strtoupper($user->username), '198.18.0.1');
        }

        $authenticator = $I->grabService(UsernameAndPasswordAuthenticator::class);

        try {
            $authenticator->authenticate($user->username, IntegrationTester::PASSWORD);
            $I->fail('A blocked username logged in.');
        } catch (AuthenticationException $exception) {
            $I->assertSame('web-user-login.error.userBlocked', $exception->getMessage());
        }

        $attempts->clearAttempts($user->username);
        $I->assertSame($user->id, $authenticator->authenticate($user->username, IntegrationTester::PASSWORD)->getId());
    }

    public function aPublicAddressIsLookedUpOnceAndThenTakenFromTheCache(IntegrationTester $I) : void
    {
        $mock = new MockHandler([
            new Response(200, [], '{"success":true,"country_code":"CZ","city":"Praha"}'),
            new Response(200, [], '{"success":true,"country_code":"XX","city":"Nowhere"}'),
        ]);
        $service = $this->geoIp($I, $mock);
        $ip = $this->publicIp();

        $lookedUp = $service->getInfo($ip);
        $cached = $service->getInfo($ip);

        $I->assertSame(['country' => 'CZ', 'city' => 'Praha'], $lookedUp);
        $I->assertSame($lookedUp, $cached);
        $I->assertSame(1, $mock->count(), 'The cached address was looked up again.');

        $request = $mock->getLastRequest();
        $I->assertNotNull($request);
        $I->assertSame('https', $request->getUri()->getScheme());
    }

    public function aFailedLookupAnswersFromAnOldCacheOrUnknown(IntegrationTester $I) : void
    {
        $ip = $this->publicIp();
        $cache = new GeoIpCacheEntity();
        $cache->ipAddress = $ip;
        $cache->countryCode = 'SK';
        $cache->city = 'Bratislava';
        $cache->updatedAt = new DateTimeImmutable('-30 days');
        $I->em()->persist($cache);
        $I->em()->flush();

        $mock = new MockHandler([
            new Response(500, [], 'down'),
            new Response(200, [], '{"success":false}'),
        ]);
        $service = $this->geoIp($I, $mock);

        $I->assertSame(['country' => 'SK', 'city' => 'Bratislava'], $service->getInfo($ip));
        $I->assertSame(['country' => '??', 'city' => '??'], $service->getInfo($this->publicIp()));
        $I->assertSame(0, $mock->count());
    }

    private function geoIp(IntegrationTester $I, MockHandler $mock) : GeoIpService
    {
        $clientFactory = new ClientFactory(new SnapshotStack());
        $clientFactory->setHandlerStack(HandlerStack::create($mock));

        return new GeoIpService($clientFactory, $I->grabService(EntityManagerDecorator::class));
    }

    /**
     * A public address (not private, not reserved) nobody has looked up yet.
     */
    private function publicIp() : string
    {
        return '81.' . random_int(0, 255) . '.' . random_int(0, 255) . '.' . random_int(1, 254);
    }
}
