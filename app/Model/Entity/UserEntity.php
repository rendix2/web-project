<?php declare(strict_types=1);

namespace App\Model\Entity;

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
use Ramsey\Uuid\Doctrine\UuidType;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

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
    #[Column(type: Types::BIGINT, unique: true)]
    public string $id;

    #[Column(type: UuidType::NAME, unique: true)]
    public UuidInterface $uuid;

    #[Column(type: Types::STRING, length: 512)]
    public string $name;

    #[Column(type: Types::STRING, length: 512)]
    public string $surname;

    #[Column(type: Types::STRING, length: 512, unique: true)]
    public string $username;

    #[Column(type: Types::STRING, length: 512, unique: true)]
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

    /**
     * @var UserPasswordRequestEntity $passwordRequests
     */
    #[OneToMany(mappedBy: 'user', targetEntity: UserPasswordRequestEntity::class, cascade: ['persist', 'remove'])]
    public Collection $passwordRequests;

    /**
     * @var UserActivationEntity[] $activationKeys
     */
    #[OneToMany(mappedBy: 'user', targetEntity: UserActivationEntity::class, cascade: ['persist', 'remove'])]
    public Collection $activationKeys;

    /**
     * @var UserAutoLoginEntity[] $activationKeys
     */
    #[OneToMany(mappedBy: 'user', targetEntity: UserAutoLoginEntity::class, cascade:  ['persist', 'remove'])]
    public Collection $autoLogins;

    /**
     * @var UserEmailEntity[] $emails
     */
    #[OneToMany(mappedBy: 'user', targetEntity: UserEmailEntity::class, cascade:  ['persist', 'remove'])]
    public Collection $emails;

    #[ManyToMany(targetEntity: RoleEntity::class, mappedBy: 'users', cascade: ['persist', 'remove'])]
    public Collection $roles;

    public function __construct()
    {
        $this->uuid = Uuid::uuid4();

        $this->lastLoginAt = null;
        $this->lastLoginCount = 0;

        $this->passwords = new ArrayCollection();
        $this->roles = new ArrayCollection();
        $this->activationKeys = new ArrayCollection();
        $this->autoLogins = new ArrayCollection();
        $this->emails = new ArrayCollection();
        $this->passwordRequests = new ArrayCollection();

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

    public function addUserPasswordRequestEntity(UserPasswordRequestEntity $userPasswordRequestEntity) : void
    {
        $this->passwordRequests->add($userPasswordRequestEntity);
    }

    public function addUserActivationEntity(UserActivationEntity $userActivationEntity) : void
    {
        $this->activationKeys->add($userActivationEntity);
    }

    public function addAutoLoginEntity(UserAutoLoginEntity $autoLoginEntity) : void
    {
        $this->autoLogins->add($autoLoginEntity);
    }

    public function addUserEmailEntity(UserEmailEntity $userEmailEntity) : void
    {
        $this->emails->add($userEmailEntity);
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
