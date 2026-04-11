<?php

namespace App\Model\Repository;

use App\Model\Entity\UserEntity;
use Doctrine\ORM\EntityRepository;

/**
 * class UserRepository
 *
 * @package App\Model\Repository
 * @extends EntityRepository<UserEntity>
 */
class UserRepository extends EntityRepository
{

    public function findOneById(string $id) : ?UserEntity
    {
        return $this
            ->findOneBy(
                [
                    'id' => $id,
                ]
            );
    }

    public function findOneByUuid(string $uuid) : ?UserEntity
    {
        return $this
            ->findOneBy(
                [
                    'uuid' => $uuid,
                ]
            );
    }

    public function findOneByEmail(string $email) : ?UserEntity
    {
        return $this
            ->findOneBy(
                [
                    'email' => $email,
                ]
            );
    }

}