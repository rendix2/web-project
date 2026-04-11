<?php

namespace App\Service;

/**
 * class GeoIpService
 *
 * @package App\Service
 */

use App\Database\EntityManagerDecorator;
use App\Model\Entity\GeoIpCacheEntity;
use Contributte\Guzzlette\ClientFactory;
use DateTimeImmutable;
use GuzzleHttp\Client;
use Nette\Utils\Json;

class GeoIpService
{

    private Client $client;

    public function __construct(
        private readonly ClientFactory          $clientFactory,
        private readonly EntityManagerDecorator $em,
    )
    {
        $this->client = $this->clientFactory->createClient([
            'base_uri' => 'http://ip-api.com/json/',
            'timeout'  => 2.0,
            'connect_timeout' => 1.5,
        ]);
    }

    /**
     * @param string $ip
     * @return string[]
     */
    public function getInfo(string $ip): array
    {
        if ($this->isLocalIp($ip)) {
            return [
                'country' => 'Local',
                'city' => 'Local'
            ];
        }

        /**
         * @var GeoIpCacheEntity $cache
         */
        $cache = $this->em
            ->getRepository(GeoIpCacheEntity::class)
            ->findOneBy(
                [
                    'ipAddress' => $ip,
                ]
            );

        if ($cache && $cache->updatedAt > new DateTimeImmutable('-7 days')) {
            return [
                'country' => $cache->countryCode,
                'city' => $cache->city
            ];
        }

        try {
            $response = $this->client->request('GET', $ip, [
                'query' => ['fields' => 'status,countryCode,city']
            ]);
            $data = Json::decode($response->getBody()->getContents());

            if (isset($data->status) && $data->status === 'success') {
                if (!$cache) {
                    $cache = new GeoIpCacheEntity();
                    $cache->ipAddress = $ip;
                }

                $cache->countryCode = $data->countryCode;
                $cache->city = $data->city;
                $cache->updatedAt = new DateTimeImmutable();

                $this->em->persist($cache);
                $this->em->flush();

                return [
                    'country' => $cache->countryCode,
                    'city' => $cache->city
                ];
            }
        } catch (\Throwable $e) {
            // Logování...
        }

        if ($cache) {
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

    private function isLocalIp(string $ip): bool
    {
        return $ip === '127.0.0.1' || $ip === '::1' || str_starts_with($ip, '192.168.') || str_starts_with($ip, '172.') || str_starts_with($ip, '10.');
    }
}
