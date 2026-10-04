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
    #[Column(type: Types::BIGINT)]
    public int $id;

    #[Column(type: Types::STRING, length: 512)]
    public string $name;

    #[Column(type: Types::TEXT)]
    public string $description;

    #[Column(type: Types::DATETIME_IMMUTABLE)]
    public DateTimeImmutable $createdAt;

    #[Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    public ?DateTimeImmutable $updatedAt;

    /**
     * @var Collection<int, UserEntity> $users
     */
    // cascade: 'remove' on a ManyToMany cascades an actual ENTITY remove, not just a join-table
    // row delete - it made deleting one Role delete every User who had it assigned. Removing a
    // role/user must only drop the user_role join row, which Doctrine already does on its own.
    #[ManyToMany(targetEntity: UserEntity::class, inversedBy: 'roles', cascade: ['persist'])]
    #[JoinTable(
        name: 'user_role',
        joinColumns: [
            new JoinColumn('role_id', 'id'),
        ],
        inverseJoinColumns: [
            new JoinColumn('user_id', 'id'),
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
