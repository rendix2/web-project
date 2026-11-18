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

#[Entity()]
#[Table(name: 'user_login_attempt')]
class UserLoginAttemptEntity
{

    #[Id()]
    #[GeneratedValue()]
    #[Column(type: Types::BIGINT, unique: true, options: ['comment' => 'ID'])]
    public int $id;

    #[Column(type: Types::STRING, length: 512, unique: false, options: ['comment' => 'Username'])]
    public string $username;

    #[Column(type: IpAddressType::NAME, nullable: false, options: ['comment' => 'IP Address'])]
    public string $ipAddress;

    #[Column(length: 512, nullable: true, options: ['comment' => 'User agent'])]
    public ?string $userAgent;

    #[Column(length: 16, nullable: true, options: ['comment' => 'Country code'])]
    public ?string $countryCode;

    #[Column(type: Types::DATETIME_IMMUTABLE, options: ['comment' => 'Created at'])]
    public DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->userAgent = null;
        $this->countryCode = null;

        $this->createdAt = new DateTimeImmutable();
    }

}
