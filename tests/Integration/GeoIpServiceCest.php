<?php declare(strict_types=1);

namespace Tests\Integration;

use App\Service\GeoIpService;
use Codeception\Attribute\DataProvider;
use Codeception\Example;
use Tests\Support\IntegrationTester;

/**
 * GeoIpService (2026-11-18): the lookup goes over HTTPS (ip-api.com of the free plan had only plain HTTP), and an address that is
 * not public never leaves the server. These tests never call the provider (no public address is looked up).
 */
class GeoIpServiceCest
{
    public function theProviderIsCalledOverHttps(IntegrationTester $I) : void
    {
        $I->assertStringStartsWith('https://', GeoIpService::BASE_URI);
    }

    /**
     * @param Example<string, string> $example
     */
    #[DataProvider('localAddresses')]
    public function aLocalAddressIsNotLookedUp(IntegrationTester $I, Example $example) : void
    {
        /** @var GeoIpService $service */
        $service = $I->grabService(GeoIpService::class);

        $I->assertSame(['country' => 'Local', 'city' => 'Local'], $service->getInfo($example['ip']));
    }

    /**
     * @return list<array{ip: string}>
     */
    protected function localAddresses() : array
    {
        return [
            ['ip' => '127.0.0.1'],
            ['ip' => '::1'],
            ['ip' => '10.1.2.3'],
            ['ip' => '172.16.0.1'],
            ['ip' => '172.31.255.254'],
            ['ip' => '192.168.79.10'],
            ['ip' => 'fd00::1'],
            ['ip' => '0.0.0.0'],
            ['ip' => 'not-an-ip'],
        ];
    }
}
