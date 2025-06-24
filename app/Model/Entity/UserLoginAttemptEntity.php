<?php declare(strict_types=1);

namespace App\Model\Entity;

use App\Model\Doctrine\Type\IpAddressType;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\Table;

#[Entity()]
#[Table(name: 'userLoginAttempt')]
class UserLoginAttemptEntity
{

    #[Id()]
    #[GeneratedValue()]
    #[Column(type: Types::INTEGER, unique: true)]
    public int $id;

    #[Column(type: Types::STRING, length: 255, unique: false)]
    public string $username;

    #[Column(name: 'ipAddress', type: IpAddressType::NAME, nullable: false)]
    public string $ipAddress;

    #[Column(name: 'createdAt', type: Types::DATETIME_IMMUTABLE)]
    public DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new DateTimeImmutable();
    }

}
