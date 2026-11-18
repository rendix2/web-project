<?php declare(strict_types=1);

namespace App\Model\Entity;

use App\Database\Types\IpAddressType;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\Table;

#[Entity()]
#[Table(name: 'user_auto_login')]
class UserAutoLoginEntity
{
    public const int LIFETIME_DAYS = 30;

    #[Id()]
    #[GeneratedValue()]
    #[Column(type: Types::BIGINT, options: ['comment' => 'ID'])]
    public int $id;

    #[ManyToOne(targetEntity: UserEntity::class, inversedBy: 'autoLogins', cascade: ['persist'])]
    #[JoinColumn(unique: false, nullable: false, onDelete: 'CASCADE', options: ['comment' => 'User ID'])]
    public UserEntity $user;

    /**
     * SHA-256 (hex) of the token in the cookie, never the token itself: a leaked table logs nobody in. Unique, so it is a plain index lookup.
     */
    #[Column(type: Types::STRING, length: 64, unique: true, options: ['comment' => 'SHA-256 of the token saved in the cookie'])]
    public string $tokenHash;

    #[Column(type: Types::DATETIME_IMMUTABLE, options: ['comment' => 'Token is valid until'])]
    public DateTimeImmutable $validUntil;

    #[Column(type: IpAddressType::NAME, nullable: false, options: ['comment' => 'IP address'])]
    public string $ipAddress;

    #[Column(length: 512, nullable: true, options: ['comment' => 'User agent'])]
    public ?string $userAgent;

    #[Column(length: 16, nullable: true, options: ['comment' => 'Country code'])]
    public ?string $countryCode;

    #[Column(type: Types::DATETIME_IMMUTABLE, options: ['comment' => 'Created at'])]
    public DateTimeImmutable $createdAt;

    #[Column(type: Types::DATETIME_IMMUTABLE, nullable: true, options: ['comment' => 'Updated at'])]
    public ?DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->userAgent = null;
        $this->countryCode = null;

        $this->validUntil = new DateTimeImmutable('+' . self::LIFETIME_DAYS . ' days');

        $this->createdAt = new DateTimeImmutable();
        $this->updatedAt = null;
    }
}
