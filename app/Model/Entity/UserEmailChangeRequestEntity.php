<?php declare(strict_types=1);

namespace App\Model\Entity;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\Table;

/**
 * A pending change of the e-mail of an account: the new address is saved to the account only when the link sent to it is opened.
 * One request per user (a new one replaces the previous). The token is stored as its SHA-256 hash.
 */
#[Entity()]
#[Table(name: 'user_email_change_request')]
class UserEmailChangeRequestEntity
{
    public const int LIFETIME_HOURS = 24;

    #[Id()]
    #[GeneratedValue()]
    #[Column(type: Types::BIGINT, options: ['comment' => 'ID'])]
    public int $id;

    #[ManyToOne(targetEntity: UserEntity::class, cascade: ['persist'])]
    #[JoinColumn(unique: true, nullable: false, onDelete: 'CASCADE', options: ['comment' => 'User ID'])]
    public UserEntity $user;

    #[Column(type: Types::STRING, length: 512, options: ['comment' => 'New email'])]
    public string $newEmail;

    #[Column(type: Types::STRING, length: 64, unique: true, options: ['comment' => 'SHA-256 of the token in the link'])]
    public string $tokenHash;

    #[Column(type: Types::DATETIMETZ_IMMUTABLE, options: ['comment' => 'Link is valid until'])]
    public DateTimeImmutable $validUntil;

    #[Column(type: Types::DATETIMETZ_IMMUTABLE, options: ['comment' => 'Created at'])]
    public DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->validUntil = new DateTimeImmutable('+' . self::LIFETIME_HOURS . ' hours');
        $this->createdAt = new DateTimeImmutable();
    }
}
