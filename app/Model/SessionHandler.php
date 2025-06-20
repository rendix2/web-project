<?php declare(strict_types=1);

namespace App\Model;

use App\Database\EntityManagerDecorator;
use App\Model\Entity\SessionEntity;
use DateInterval;
use DateTimeImmutable;
use SessionHandlerInterface;
use Throwable;

class SessionHandler implements SessionHandlerInterface
{

    public function __construct(
        private readonly EntityManagerDecorator $em,
    ) {
    }

    public function open(string $path, string $name) : bool
    {
        return true;
    }

    public function close() : bool
    {
        return true;
    }

    public function read(string $id) : string|false
    {
        if (!$this->isValidSessionId($id)) {
            return false;
        }

        try {
            /** @var ?SessionEntity $session */
            $session = $this->em
                ->getRepository(SessionEntity::class)
                ->findOneBy(
                    [
                        'id' => $id,
                    ]
                );

            return $session?->data ?? '';
        } catch (Throwable $e) {
            return false;
        }
    }

    public function write(string $id, string $data) : bool
    {
        if (!$this->isValidSessionId($id)) {
            return false;
        }

        try {
            /** @var ?SessionEntity $session */
            $session = $this->em
                ->getRepository(SessionEntity::class)
                ->findOneBy(
                    [
                        'id' => $id,
                    ]
                );

            if (!$session) {
                $session = new SessionEntity;
                $session->id = $id;
            }

            $session->data = $data;
            $session->updatedAt = new DateTimeImmutable();

            $this->em->persist($session);
            $this->em->flush();

            return true;
        } catch (Throwable $e) {
            return false;
        }
    }

    public function destroy(string $id) : bool
    {
        if (!$this->isValidSessionId($id)) {
            return false;
        }

        try {
            $session = $this->em
                ->getRepository(SessionEntity::class)
                ->findOneBy(
                    [
                        'id' => $id,
                    ]
                );

            if ($session) {
                $this->em->remove($session);
                $this->em->flush();
            }

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    public function gc(int $max_lifetime) : int|false
    {
        try {
            $expireTime = (new DateTimeImmutable())->sub(new DateInterval("PT{$max_lifetime}S"));

            $query = $this->em->createQueryBuilder()
                ->delete(SessionEntity::class, 's')
                ->where('s.updatedAt < :expireTime')
                ->setParameter('expireTime', $expireTime)
                ->getQuery();

            return $query->execute();
        } catch (Throwable) {
            return false;
        }
    }

    private function isValidSessionId(string $id): bool
    {
        return preg_match('/^[a-zA-Z0-9,-]{16,128}$/', $id) === 1;
    }

}
