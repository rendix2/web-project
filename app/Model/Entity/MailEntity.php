<?php declare(strict_types=1);

namespace App\Model\Entity;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\Index;
use Doctrine\ORM\Mapping\Table;

#[Entity()]
#[Table(name: 'mail')]
#[Index(name: 'K__Mail__Email_to', columns: ['email_to'])]
class MailEntity
{
    #[Id()]
    #[GeneratedValue()]
    #[Column(type: Types::BIGINT, options: ['comment' => 'ID'])]
    public int $id;

    #[Column(type: Types::STRING, length: 512, nullable: false, options: ['comment' => 'Email to'])]
    public string $emailTo;

    #[Column(type: Types::STRING, length: 1024, nullable: false, options: ['comment' => 'Subject'])]
    public string $subject;

    #[Column(type: Types::TEXT, nullable: false, options: ['comment' => 'Body'])]
    public string $body;

    #[Column(type: Types::DATETIMETZ_IMMUTABLE, options: ['comment' => 'Created at'])]
    public DateTimeImmutable $createdAt;

    #[Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true, options: ['comment' => 'Updated at'])]
    public ?DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->createdAt = new DateTimeImmutable();
        $this->updatedAt = null;
    }
}
