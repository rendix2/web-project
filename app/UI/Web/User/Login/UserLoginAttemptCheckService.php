<?php declare(strict_types=1);

namespace App\UI\Web\User\Login;

use App\Database\EntityManagerDecorator;
use App\Model\Entity\UserLoginAttemptEntity;
use DateTimeImmutable;
use InvalidArgumentException;

class UserLoginAttemptCheckService
{

    private int $maxAttempts;

    private int $lockTimeSeconds;

    public function __construct(
        private readonly EntityManagerDecorator $em,
    ) {
        $this->maxAttempts = 5;
        $this->lockTimeSeconds = 900;
    }

    public function logAttempt(string $username, string $ipAddress): void
    {
        if (!filter_var($ipAddress, FILTER_VALIDATE_IP)) {
            throw new InvalidArgumentException("Neplatná IP adresa: $ipAddress");
        }

        $userLoginAttemptEntity = new UserLoginAttemptEntity();
        $userLoginAttemptEntity->username = $username;
        $userLoginAttemptEntity->ipAddress = $ipAddress;

        $this->em->persist($userLoginAttemptEntity);
        $this->em->flush();
    }

    public function isIpBlocked(string $ipAddress): bool
    {
        $since = new DateTimeImmutable("-{$this->lockTimeSeconds} seconds");

        if (!filter_var($ipAddress, FILTER_VALIDATE_IP)) {
            throw new InvalidArgumentException("Neplatná IP adresa: $ipAddress");
        }

        $count = $this->em
            ->getRepository(UserLoginAttemptEntity::class)
            ->createQueryBuilder('_attempt')

            ->select('count(_attempt.id)')

            ->where('_attempt.ipAddress = :ipAddress')
            ->setParameter('ipAddress', $ipAddress)

            ->andWhere('_attempt.createdAt >= :since')
            ->setParameter('since', $since)

            ->getQuery()
            ->getSingleScalarResult();

        return $count >= $this->maxAttempts;
    }

    public function isUserNameBlocked(string $username): bool
    {
        $since = new DateTimeImmutable("-{$this->lockTimeSeconds} seconds");

        $count = $this->em
            ->getRepository(UserLoginAttemptEntity::class)
            ->createQueryBuilder('_attempt')

            ->select('count(_attempt.id)')

            ->where('_attempt.username = :username')
            ->setParameter('username', $username)

            ->andWhere('_attempt.createdAt >= :since')
            ->setParameter('since', $since)

            ->getQuery()
            ->getSingleScalarResult();

        return $count >= $this->maxAttempts;
    }

    public function clearAttempts(string $username, string $ipAddress): void
    {
        $this->em
            ->createQueryBuilder()
            ->delete(UserLoginAttemptEntity::class, 'a')
            ->where('a.username = :username')
            ->setParameter('username', $username)
            ->getQuery()
            ->execute();

        if (!filter_var($ipAddress, FILTER_VALIDATE_IP)) {
            throw new InvalidArgumentException("Neplatná IP adresa při mazání: $ipAddress");
        }

        $this->em
            ->createQueryBuilder()
            ->delete(UserLoginAttemptEntity::class, 'a')
            ->where('a.ipAddress = :ipAddress')
            ->setParameter('ipAddress', $ipAddress)
            ->getQuery()
            ->execute();
    }

}
