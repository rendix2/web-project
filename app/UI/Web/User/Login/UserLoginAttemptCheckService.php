<?php declare(strict_types=1);

namespace App\UI\Web\User\Login;

use App\Database\EntityManagerDecorator;
use App\Model\Entity\UserLoginAttemptEntity;
use DateTimeImmutable;
use Nette\Mail\Mailer;
use Nette\Mail\Message;

class UserLoginAttemptCheckService
{

    private int $maxAttempts;

    private int $lockTimeSeconds;

    public function __construct(
        private readonly EntityManagerDecorator $em,
        private readonly Mailer $mailer,
    ) {
        $this->maxAttempts = 5;
        $this->lockTimeSeconds = 900;
    }

    public function logAttempt(string $username, string $ipAddress): void
    {
        if (!filter_var($ipAddress, FILTER_VALIDATE_IP)) {
            throw new \InvalidArgumentException("Neplatná IP adresa: $ipAddress");
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
            throw new \InvalidArgumentException("Neplatná IP adresa: $ipAddress");
        }

        $count = $this->em
            ->getRepository(UserLoginAttemptEntity::class)
            ->createQueryBuilder('_attempt')

            ->select('count(_attempt.id)')

            ->where('_attempt.ipAddress = :ipAddress')
            ->setParameter('ipAddress', str_pad(inet_pton($ipAddress), 16, "\0", STR_PAD_RIGHT))

            ->andWhere('_attempt.createdAt >= :since')
            ->setParameter('since', $since)

            ->getQuery()
            ->getSingleScalarResult();

        return $count >= $this->maxAttempts;
    }

    public function isLocked(string $username): bool
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

        if ($count >= $this->maxAttempts) {
            $this->sendAlertEmail($username, $count);
            return true;
        }

        return false;
    }

    private function sendAlertEmail(string $username, int $count): void
    {
        $mail = new Message();
        $mail->setFrom('noreply@chatbot.cz')
            ->addTo('admin@chatbot.cz')
            ->setSubject('Podezřelá aktivita při přihlašování')
            ->setBody("Uživatel '{$username}' se pokusil {$count}x přihlásit neúspěšně během posledních 15 minut.");

        $this->mailer->send($mail);
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

        $this->em
            ->createQueryBuilder()
            ->delete(UserLoginAttemptEntity::class, 'a')
            ->where('a.ipAddress = :ipAddress')
            ->setParameter('ipAddress', inet_pton($ipAddress))
            ->getQuery()
            ->execute();
    }

}
