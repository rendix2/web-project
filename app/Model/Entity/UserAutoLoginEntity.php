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
#[Table(name: 'userAutoLogin')]
class UserAutoLoginEntity
{

    #[Id()]
    #[GeneratedValue()]
    #[Column(type: Types::INTEGER)]
    public int $id;

    #[ManyToOne(targetEntity: UserEntity::class)]
    #[JoinColumn('userId', unique: false, nullable: false)]
    public UserEntity $user;

    #[Column(type: Types::STRING, length: 1024)]
    public string $token;

    #[Column(name: 'ipAddress', type: Types::BINARY, length: 16)]
    public $ipAddress;

    #[Column(name: 'createdAt', type: Types::DATETIME_IMMUTABLE)]
    public DateTimeImmutable $createdAt;

    #[Column(name: 'updatedAt', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    public ?DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->createdAt = new DateTimeImmutable();
        $this->updatedAt = null;
    }

    public function getIpAddress() : string
    {
        return inet_ntop(stream_get_contents($this->ipAddress));
    }

    public function setIpAddress(string $ipAddress) : void
    {
        $this->ipAddress = inet_pton($ipAddress);
    }

}