<?php declare(strict_types=1);

namespace App\Model\Entity;

use App\Database\Types\IpAddressType;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\Index;
use Doctrine\ORM\Mapping\Table;

/**
 * class UserPasswordRequestAttemptEntity
 *
 * Slouží k omezení počtu žádostí o obnovu hesla (rate limiting) - stejný princip jako
 * UserLoginAttemptEntity u loginu, jen klíčované e-mailem místo uživatelského jména
 * (formulář o obnovu hesla se ptá na e-mail, ne na username).
 *
 * @package App\Model\Entity
 */
#[Entity()]
#[Table(name: 'user_password_request_attempt')]
#[Index(name: 'K__User_password_request_attempt__Email_created_at', columns: ['email', 'created_at'])]
#[Index(name: 'K__User_password_request_attempt__Ip_address_created_at', columns: ['ip_address', 'created_at'])]
class UserPasswordRequestAttemptEntity
{
    #[Id()]
    #[GeneratedValue()]
    #[Column(type: Types::BIGINT, unique: true, options: ['comment' => 'ID'])]
    public int $id;

    #[Column(type: Types::STRING, length: 512, options: ['comment' => 'Email'])]
    public string $email;

    #[Column(type: IpAddressType::NAME, nullable: false, options: ['comment' => 'IP Address'])]
    public string $ipAddress;

    #[Column(type: Types::DATETIMETZ_IMMUTABLE, options: ['comment' => 'Created at'])]
    public DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new DateTimeImmutable();
    }
}
