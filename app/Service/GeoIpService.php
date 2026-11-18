<?php declare(strict_types=1);

namespace App\Service;

use App\Database\EntityManagerDecorator;
use App\Model\Entity\GeoIpCacheEntity;
use Contributte\Guzzlette\ClientFactory;
use DateTimeImmutable;
use GuzzleHttp\Client;
use Nette\Utils\Json;
use Throwable;
use Tracy\Debugger;
use Tracy\ILogger;

/**
 * The country and the city of an IP address (the records of logins and of "stay logged in"), cached in the database for 7 days.
 *
 * Provider: ipwho.is over HTTPS (no key; `fields` limits the answer to what is stored). Until 2026-11-18 it was ip-api.com,
 * whose free plan has no HTTPS at all: every lookup sent the IP address of a visitor over plain HTTP.
 * A private, loopback or reserved address is never sent out ("Local").
 */
class GeoIpService
{
    public const string BASE_URI = 'https://ipwho.is/';

    private Client $client;

    public function __construct(
        private readonly ClientFactory          $clientFactory,
        private readonly EntityManagerDecorator $em,
    )
    {
        $this->client = $this->clientFactory->createClient([
            'base_uri' => self::BASE_URI,
            'timeout'  => 2.0,
            'connect_timeout' => 1.5,
        ]);
    }

    /**
     * @return array{country: string, city: string}
     */
    public function getInfo(string $ip): array
    {
        if ($this->isLocalIp($ip)) {
            return [
                'country' => 'Local',
                'city' => 'Local'
            ];
        }

        $cache = $this->em
            ->getRepository(GeoIpCacheEntity::class)
            ->findOneBy(
                [
                    'ipAddress' => $ip,
                ]
            );

        if ($cache !== null && $cache->updatedAt > new DateTimeImmutable('-7 days')) {
            return [
                'country' => $cache->countryCode,
                'city' => $cache->city
            ];
        }

        try {
            $response = $this->client->request('GET', rawurlencode($ip), [
                'query' => ['fields' => 'success,country_code,city']
            ]);
            $data = Json::decode($response->getBody()->getContents(), true);

            if (is_array($data) && ($data['success'] ?? null) === true) {
                if ($cache === null) {
                    $cache = new GeoIpCacheEntity();
                    $cache->ipAddress = $ip;
                }

                $cache->countryCode = is_string($data['country_code'] ?? null) ? $data['country_code'] : '??';
                $cache->city = is_string($data['city'] ?? null) ? $data['city'] : '??';
                $cache->updatedAt = new DateTimeImmutable();

                $this->em->persist($cache);
                $this->em->flush();

                return [
                    'country' => $cache->countryCode,
                    'city' => $cache->city
                ];
            }
        } catch (Throwable $exception) {
            // the lookup is only extra information of a login: it never stops one
            Debugger::log($exception, ILogger::WARNING);
        }

        if ($cache !== null) {
            return [
                'country' => $cache->countryCode,
                'city' => $cache->city
            ];
        }

        return [
            'country' => '??',
            'city' => '??'
        ];
    }

    /**
     * Loopback, private (10/8, 172.16/12, 192.168/16, fc00::/7) and reserved ranges, and anything that is not an IP address.
     */
    private function isLocalIp(string $ip): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return true;
        }

        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    }
}
