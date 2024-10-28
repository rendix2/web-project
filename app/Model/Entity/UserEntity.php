<?php declare(strict_types=1);

namespace App\Model\Entity;

use Chatbot\App\Model\Entity\LogWebMessageEntity;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\JoinTable;
use Doctrine\ORM\Mapping\ManyToMany;
use Doctrine\ORM\Mapping\OneToMany;
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

    #[Column(name: 'lastLoginAt', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    public ?DateTimeImmutable $lastLoginAt;

    #[Column(name: 'lastLoginCount', type: Types::INTEGER)]
    public int $lastLoginCount;

    #[Column(name: 'createdAt', type: Types::DATETIME_IMMUTABLE)]
    public DateTimeImmutable $createdAt;

    #[Column(name: 'updatedAt', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    public ?DateTimeImmutable $updatedAt;

    /**
     * @var UserPasswordEntity[] $passwords
     */
    #[OneToMany(mappedBy: 'user', targetEntity: UserPasswordEntity::class, cascade: ['persist', 'remove'])]
    public Collection $passwords;


    #[ManyToMany(targetEntity: RoleEntity::class, mappedBy: 'users', cascade: ['persist', 'remove'])]
    #[JoinTable(
        name: 'userRole',
        joinColumns: [
            new JoinColumn('userId', 'id'),

        ],
        inverseJoinColumns: [
            new JoinColumn('roleId', 'id'),
        ]
    )]
    public Collection $roles;

    public function __construct()
    {
        $this->lastLoginAt = null;
        $this->lastLoginCount = 0;

        $this->passwords = new ArrayCollection();
        $this->roles = new ArrayCollection();

        $this->createdAt = new DateTimeImmutable();
        $this->updatedAt = null;
    }

    public function addRoleEntity(RoleEntity $roleEntity) : void
    {
        $this->roles->add($roleEntity);
        $roleEntity->users->add($this);
    }

    public function removeRoleEntity(RoleEntity $roleEntity) : void
    {
        $this->roles->removeElement($roleEntity);
        $roleEntity->users->removeElement($this);
    }

    public function addUserPasswordEntity(UserPasswordEntity $userPasswordEntity) : void
    {
        $this->passwords->add($userPasswordEntity);
    }

    public function setPassword(string $password) : void
    {
        $this->password = $password;

        $userPassword = new UserPasswordEntity();
        $userPassword->user = $this;
        $userPassword->password = $password;

        $this->addUserPasswordEntity($userPassword);
    }

}
