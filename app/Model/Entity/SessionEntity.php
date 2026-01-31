<?php declare(strict_types=1);

namespace App\Model\Entity;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\Table;

#[Entity()]
#[Table(name: 'session')]
class SessionEntity
{

    #[Id]
    #[Column(type: Types::STRING, length: 128, unique: true, nullable: false)]
    public string $id;

    #[Column(type: Types::TEXT, nullable: false)]
    public string $data;

    #[Column(type: Types::DATETIME_IMMUTABLE, nullable: false)]
    public ?DateTimeImmutable $createdAt;

    #[Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    public ?DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->createdAt = new DateTimeImmutable();
        $this->updatedAt = null;
    }

}