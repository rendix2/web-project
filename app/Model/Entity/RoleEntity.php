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
use Doctrine\ORM\Mapping\Table;

#[Entity()]
#[Table(name: 'role')]
class RoleEntity
{

    #[Id()]
    #[GeneratedValue()]
    #[Column(type: Types::INTEGER)]
    public int $id;

    #[Column(type: Types::STRING, length: 512)]
    public string $name;

    #[Column(type: Types::TEXT)]
    public string $description;

    #[Column(name: 'createdAt', type: Types::DATETIME_IMMUTABLE)]
    public DateTimeImmutable $createdAt;

    #[Column(name: 'updatedAt', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    public ?DateTimeImmutable $updatedAt;

    #[ManyToMany(targetEntity: UserEntity::class, inversedBy: 'roles', cascade: ['persist', 'remove'])]
    #[JoinTable(
        name: 'userRole',
        joinColumns: [
            new JoinColumn('roleId', 'id'),

        ],
        inverseJoinColumns: [
            new JoinColumn('userId', 'id'),
        ]
    )]
    public Collection $users;

    public function __construct()
    {
        $this->createdAt = new DateTimeImmutable();
        $this->updatedAt = null;

        $this->users = new ArrayCollection();
    }

}
