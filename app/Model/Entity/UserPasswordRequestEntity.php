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

#[Entity()]
#[Table(name: 'user_password_request')]
class UserPasswordRequestEntity
{
    #[Id()]
    #[GeneratedValue()]
    #[Column(type: Types::BIGINT, options: ['comment' => 'ID'])]
    public int $id;

    #[ManyToOne(targetEntity: UserEntity::class, inversedBy: 'passwordRequests', cascade: ['persist'])]
    #[JoinColumn(unique: true, nullable: false, onDelete: 'CASCADE', options: ['comment' => 'User ID'])]
    public UserEntity $user;

    #[Column(type: Types::STRING, length: 1024, options: ['comment' => 'Forget password key'])]
    public string $forgetKey;

    #[Column(type: Types::DATETIMETZ_IMMUTABLE, options: ['comment' => 'Key is valid until'])]
    public DateTimeImmutable $validUntil;

    #[Column(type: Types::DATETIMETZ_IMMUTABLE, options: ['comment' => 'Created at'])]
    public DateTimeImmutable $createdAt;

    #[Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true, options: ['comment' => 'Updated at'])]
    public ?DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->validUntil = new DateTimeImmutable('+1 day');
        $this->createdAt = new DateTimeImmutable();
        $this->updatedAt = null;
    }
}
