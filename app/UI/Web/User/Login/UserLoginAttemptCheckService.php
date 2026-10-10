<?php declare(strict_types=1);

namespace App\UI\Web\User\Login;

use App\Database\EntityManagerDecorator;
use App\Model\Entity\UserLoginAttemptEntity;
use App\Service\GeoIpService;
use DateTimeImmutable;
use donatj\UserAgent\UserAgentParser;
use InvalidArgumentException;

class UserLoginAttemptCheckService
{

    private int $maxAttempts;

    private int $lockTimeSeconds;

    public function __construct(
        private readonly EntityManagerDecorator $em,
        private readonly GeoIpService           $geoIpService,
    ) {
        $this->maxAttempts = 5;
        $this->lockTimeSeconds = 900;
    }

    public function logAttempt(string $username, string $ipAddress): void
    {
        if (filter_var($ipAddress, FILTER_VALIDATE_IP) === false) {
            throw new InvalidArgumentException("Neplatná IP adresa: $ipAddress");
        }

        $geoIpArray = $this->geoIpService->getInfo($ipAddress);

        $parser = new UserAgentParser();
        $ua = $parser->parse();

        $userLoginAttemptEntity = new UserLoginAttemptEntity();
        $userLoginAttemptEntity->username = $this->normalize($username);
        $userLoginAttemptEntity->ipAddress = $ipAddress;
        $userLoginAttemptEntity->userAgent = $ua->browser();
        $userLoginAttemptEntity->countryCode = $geoIpArray['country'];

        $this->em->persist($userLoginAttemptEntity);
        $this->em->flush();
    }

    public function isIpBlocked(string $ipAddress): bool
    {
        $since = new DateTimeImmutable("-{$this->lockTimeSeconds} seconds");

        if (filter_var($ipAddress, FILTER_VALIDATE_IP) === false) {
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

            ->where('LOWER(_attempt.username) = :username')
            ->setParameter('username', $this->normalize($username))

            ->andWhere('_attempt.createdAt >= :since')
            ->setParameter('since', $since)

            ->getQuery()
            ->getSingleScalarResult();

        return $count >= $this->maxAttempts;
    }

    /**
     * A successful login clears the attempts of THAT USERNAME. Never those of the IP: whoever owns any account would reset the limit
     * of his IP between the guesses of the passwords of the others (password spraying).
     */
    public function clearAttempts(string $username) : void
    {
        $this->em
            ->createQueryBuilder()
            ->delete(UserLoginAttemptEntity::class, 'a')

            ->where('LOWER(a.username) = :username')
            ->setParameter('username', $this->normalize($username))

            ->getQuery()
            ->execute();
    }

    /**
     * `Admin` and `admin` are one account, so one counter (a different case must not avoid the limit).
     */
    private function normalize(string $username) : string
    {
        return mb_strtolower($username);
    }

}
