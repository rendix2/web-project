<?php

namespace App\Model\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\Table;
use Nette\Utils\DateTime;

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
	#[Column(name: 'id', type: Types::BIGINT, unique: true, nullable: false)]
	public int $id;

	#[Column(name: 'name', type: Types::STRING, unique: false, nullable: false)]
	public string $name;

	#[Column(name: 'surname', type: Types::STRING, unique: false, nullable: false)]
	public string $surname;

	#[Column(name: 'username', type: Types::STRING, unique: true, nullable: false)]
	public string $username;

	#[Column(name: 'email', type: Types::STRING, unique: true, nullable: false)]
	public string $email;

	#[Column(name: 'password', type: Types::STRING, unique: true, nullable: false)]
	public string $password;

	#[Column(name: 'isActive', type: Types::BOOLEAN, unique: false, nullable: false)]
	public bool $isActive;

	#[Column(name: 'createdAt', type: Types::DATETIME_IMMUTABLE, nullable: false)]
	public DateTime $createdAt;

	#[Column(name: 'updatedAt', type: Types::DATETIME_IMMUTABLE, nullable: true)]
	public ?DateTime $updatedAt;

	public function __construct()
	{
		$this->createdAt = new DateTime();
		$this->updatedAt = null;
	}

}
