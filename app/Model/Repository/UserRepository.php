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

    /**
     * `John@x.cz` and `john@x.cz` are one e-mail.
     */
    public function findOneByEmail(string $email) : ?UserEntity
    {
        /** @var ?UserEntity $user */
        $user = $this
            ->createQueryBuilder('u')
            ->where('LOWER(u.email) = :email')
            ->setParameter('email', mb_strtolower(trim($email)))
            ->getQuery()
            ->getOneOrNullResult();

        return $user;
    }

    /**
     * `Admin` and `admin` are one account.
     */
    public function findOneByUsername(string $username) : ?UserEntity
    {
        /** @var ?UserEntity $user */
        $user = $this
            ->createQueryBuilder('u')
            ->where('LOWER(u.username) = :username')
            ->setParameter('username', mb_strtolower($username))
            ->getQuery()
            ->getOneOrNullResult();

        return $user;
    }

}
