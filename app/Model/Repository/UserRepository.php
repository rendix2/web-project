<?php

namespace App\Model\Repository;

use App\Model\Entity\UserEntity;
use Doctrine\ORM\EntityRepository;

/**
 * class UserRepository
 *
 * @package App\Model\Repository
 */
class UserRepository extends EntityRepository
{

    public function findOneById(string $id)
    {
        return $this
            ->findOneBy(
                [
                    'id' => $id,
                ]
            );
    }

    public function findOneByUuid(string $uuid)
    {
        return $this
            ->findOneBy(
                [
                    'uuid' => $uuid,
                ]
            );
    }

    public function findOneByEmail(string $email)
    {
        return $this
            ->findOneBy(
                [
                    'email' => $email,
                ]
            );
    }

}