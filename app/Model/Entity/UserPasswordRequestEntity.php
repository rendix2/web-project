<?php

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
#[Table(name: 'userPasswordRequest')]
class UserPasswordRequestEntity
{
    #[Id()]
    #[GeneratedValue()]
    #[Column(type: Types::INTEGER)]
    public int $id;

    #[ManyToOne(targetEntity: UserEntity::class)]
    #[JoinColumn('userId', unique: true, nullable: false)]
    public UserEntity $user;

    #[Column(name: 'forgetKey', type: Types::STRING, length: 1024)]
    public string $forgetKey;

    #[Column(name: 'validUntil', type: Types::DATETIME_IMMUTABLE)]
    public DateTimeImmutable $validUntil;


    #[Column(name: 'createdAt', type: Types::DATETIME_IMMUTABLE)]
    public DateTimeImmutable $createdAt;

    #[Column(name: 'updatedAt', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    public ?DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->validUntil = new DateTimeImmutable('+1 day');
        $this->createdAt = new DateTimeImmutable();
        $this->updatedAt = null;
    }

}
