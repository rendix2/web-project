<?php declare(strict_types=1);

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
    #[Column(type: Types::BIGINT, options: ['comment' => 'ID'])]
    public int $id;

    #[Column(type: IpAddressType::NAME, nullable: false, options: ['comment' => 'IP Address'])]
    public string $ipAddress;

    #[Column(type: Types::STRING, length: 2, options: ['comment' => 'Country code'])]
    public string $countryCode;

    #[Column(type: Types::STRING, length: 255, options: ['comment' => 'City'])]
    public string $city;

    #[Column(type: Types::DATETIMETZ_IMMUTABLE, options: ['comment' => 'Created at'])]
    public DateTimeImmutable $createdAt;

    #[Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true, options: ['comment' => 'Updated at'])]
    public ?DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->createdAt = new DateTimeImmutable();
        $this->updatedAt = null;
    }
}
