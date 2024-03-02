<?php

namespace App\Model\Entity;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\Table;

/**
 * class UserEntity
 *
 * @package App\Model\Entity
 */
#[Entity()]
#[Table(name: 'user')]
class UserEntity
{
	#[Id()]
	#[GeneratedValue()]
	#[Column(type: Types::BIGINT)]
	public string $id;

	#[Column(type: Types::STRING, length: 512)]
	public string $name;

	#[Column(type: Types::STRING, length: 512)]
	public string $surname;

	#[Column(type: Types::STRING, length: 512, unique: true)]
	public string $username;

	#[Column(type: Types::STRING, length: 1024, unique: true)]
	public string $email;

	#[Column(type: Types::STRING, length: 1024)]
	public string $password;

    #[Column(name: 'isActive', type: Types::BOOLEAN)]
	public bool $isActive;

	#[Column(name: 'createdAt',type: Types::DATETIME_IMMUTABLE)]
	public DateTimeImmutable $createdAt;

	#[Column(name: 'updatedAt',type: Types::DATETIME_IMMUTABLE, nullable: true)]
	public ?DateTimeImmutable $updatedAt;

	public function __construct()
	{
		$this->createdAt = new DateTimeImmutable();
		$this->updatedAt = null;
	}

}
