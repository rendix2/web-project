<?php

namespace App\Model\Entity;

use App\Database\Types\IpAddressType;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\Table;

/**
 * class GeoIpCacheEntity
 *
 * @package App\Model\Entity
 */
#[Entity()]
#[Table(name: 'geo_ip_cache')]
class GeoIpCacheEntity
{
    #[Id()]
    #[GeneratedValue()]
    #[Column(type: Types::BIGINT)]
    public string $id;

    #[Column(type: IpAddressType::NAME, nullable: false)]
    public string $ipAddress;

    #[Column(type: Types::STRING, length: 2)]
    public string $countryCode;

    #[Column(type: Types::STRING, length: 255)]
    public string $city;

    #[Column(type: Types::DATETIME_IMMUTABLE)]
    public DateTimeImmutable $createdAt;

    #[Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    public ?DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->createdAt = new DateTimeImmutable();
        $this->updatedAt = null;
    }

}
