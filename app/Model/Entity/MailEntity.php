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
#[Table(name: 'mail')]
class MailEntity
{

    #[Id()]
    #[GeneratedValue()]
    #[Column(type: Types::BIGINT)]
    public string $id;

    #[Column(name: 'emailTo', type: Types::STRING, nullable: false)]
    public string $emailTo;

    //#[ManyToOne(targetEntity: UserEmailEntity::class)]
    //#[JoinColumn('emailTo', unique: true, nullable: false)]
    //public UserEmailEntity $userEmail;

    #[Column(type: Types::STRING, length: 1024, nullable: false)]
    public string $subject;

    #[Column(type: Types::TEXT, nullable: false)]
    public string $body;

    #[Column(name: 'createdAt', type: Types::DATETIME_IMMUTABLE)]
    public DateTimeImmutable $createdAt;

    #[Column(name: 'updatedAt', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    public ?DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->createdAt = new DateTimeImmutable();
        $this->updatedAt = null;
    }

}
