<?php declare(strict_types=1);

namespace App\UI\Web\User\ForgetPassword\Request;

use App\Database\EntityManagerDecorator;
use App\Model\Entity\UserPasswordRequestAttemptEntity;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * class UserPasswordRequestAttemptCheckService
 *
 * Rate limiting žádostí o obnovu hesla - stejný princip jako UserLoginAttemptCheckService
 * u loginu (app/UI/Web/User/Login/), ale s vlastní tabulkou/limity (žádost o heslo se
 * nemá počítat do login pokusů a naopak - jsou to nezávislá rizika: login throttling brání
 * uhodnutí hesla, tohle brání spamování cizí schránky odkazy na obnovu hesla).
 *
 * @package App\UI\Web\User\ForgetPassword\Request
 */
class UserPasswordRequestAttemptCheckService
{
    private int $maxAttempts;

    private int $lockTimeSeconds;

    public function __construct(
        private readonly EntityManagerDecorator $em,
    ) {
        $this->maxAttempts = 5;
        $this->lockTimeSeconds = 900;
    }

    public function logAttempt(string $email, string $ipAddress) : void
    {
        if (filter_var($ipAddress, FILTER_VALIDATE_IP) === false) {
            throw new InvalidArgumentException("Neplatná IP adresa: $ipAddress");
        }

        $userPasswordRequestAttemptEntity = new UserPasswordRequestAttemptEntity();
        $userPasswordRequestAttemptEntity->email = mb_strtolower($email);
        $userPasswordRequestAttemptEntity->ipAddress = $ipAddress;

        $this->em->persist($userPasswordRequestAttemptEntity);
        $this->em->flush();
    }

    public function isIpBlocked(string $ipAddress) : bool
    {
        if (filter_var($ipAddress, FILTER_VALIDATE_IP) === false) {
            throw new InvalidArgumentException("Neplatná IP adresa: $ipAddress");
        }

        $since = new DateTimeImmutable("-{$this->lockTimeSeconds} seconds");

        $count = $this->em
            ->getRepository(UserPasswordRequestAttemptEntity::class)
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

    public function isEmailBlocked(string $email) : bool
    {
        $since = new DateTimeImmutable("-{$this->lockTimeSeconds} seconds");

        $count = $this->em
            ->getRepository(UserPasswordRequestAttemptEntity::class)
            ->createQueryBuilder('_attempt')

            ->select('count(_attempt.id)')

            ->where('LOWER(_attempt.email) = :email')
            ->setParameter('email', mb_strtolower($email))

            ->andWhere('_attempt.createdAt >= :since')
            ->setParameter('since', $since)

            ->getQuery()
            ->getSingleScalarResult();

        return $count >= $this->maxAttempts;
    }
}
