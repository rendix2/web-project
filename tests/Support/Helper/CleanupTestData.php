<?php declare(strict_types=1);

namespace Tests\Support\Helper;

use App\Database\EntityManagerDecorator;
use App\Model\Entity\MailEntity;
use App\Model\Entity\UserActivationEntity;
use App\Model\Entity\UserAutoLoginEntity;
use App\Model\Entity\UserEmailEntity;
use App\Model\Entity\UserEntity;
use App\Model\Entity\UserLoginAttemptEntity;
use App\Model\Entity\UserPasswordEntity;
use App\Model\Entity\UserPasswordRequestEntity;
use Codeception\Module;
use Codeception\TestInterface;
use Contributte\Codeception\Module\NetteDIModule;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Events;
use Throwable;

/**
 * Deletes what a test created, so the (real, development) database does not fill up with test data.
 *
 * Precise on purpose, no pattern-based deleting:
 *  - a Doctrine listener records every entity persisted through the test's EntityManager,
 *  - rows created by the web server during the test (form posts, ...) are registered by the test itself:
 *    $I->trackCreatedEntity(UserEntity::class, $id),
 *  - after the test the recorded entities and rows that hang on the recorded users (passwords, e-mails, activation
 *    keys, auto logins, roles, mails sent to them, login attempts) are deleted by id.
 * A record that is still referenced by something else is simply left in place.
 */
class CleanupTestData extends Module
{
    /** @var list<array{class-string, string}> */
    private array $created = [];

    /**
     * The EntityManager of the test's own container, kept from _before(): NetteDIModule throws that container away
     * before our _after(), and grabbing the service again would create a second container + connection.
     */
    private ?EntityManagerDecorator $em = null;

    public function _before(TestInterface $test) : void
    {
        $this->created = [];
        $this->em = $this->grabEm();

        $this->em->getEventManager()->addEventListener(
            [Events::postPersist],
            new class($this) {
                public function __construct(private readonly CleanupTestData $module)
                {
                }

                public function postPersist(PostPersistEventArgs $args) : void
                {
                    $entity = $args->getObject();
                    $id = $args->getObjectManager()->getClassMetadata($entity::class)->getIdentifierValues($entity)['id'] ?? null;

                    if (is_int($id) || is_string($id)) {
                        $this->module->trackCreatedEntity($entity::class, (string) $id);
                    }
                }
            }
        );
    }

    /**
     * For rows created by the web server (not through this test's EntityManager).
     *
     * @param class-string $class
     */
    public function trackCreatedEntity(string $class, int|string $id) : void
    {
        $this->created[] = [$class, (string) $id];
    }

    public function _after(TestInterface $test) : void
    {
        try {
            $this->cleanup();
        } catch (Throwable $exception) {
            // never turn a green test red because of the cleanup, but leave a trace
            codecept_debug('CleanupTestData failed: ' . $exception->getMessage());
        }

        $this->created = [];

        // release the test's connection, otherwise a long suite runs into the database's max_connections
        try {
            $this->em?->clear();
            $this->em?->getConnection()->close();
        } catch (Throwable) {
        }

        $this->em = null;
    }

    private function cleanup() : void
    {
        $em = $this->em();
        $em->clear();

        /** @var array<class-string, list<string>> $ids */
        $ids = [];

        foreach ($this->created as [$class, $id]) {
            $ids[$class][] = $id;
        }

        $userIds = $ids[UserEntity::class] ?? [];

        if ($userIds !== []) {
            $rows = $em->createQuery('SELECT u.email, u.username FROM ' . UserEntity::class . ' u WHERE u.id IN (:ids)')
                ->setParameter('ids', $userIds)
                ->getArrayResult();
            $emails = array_column($rows, 'email');
            $usernames = array_column($rows, 'username');

            $em->getConnection()->executeStatement(
                'DELETE FROM user_role WHERE user_id IN (' . implode(',', array_map('intval', $userIds)) . ')'
            );

            foreach ([UserPasswordEntity::class, UserEmailEntity::class, UserActivationEntity::class, UserAutoLoginEntity::class, UserPasswordRequestEntity::class] as $class) {
                $this->deleteWhere($class, 'e.user IN (:ids)', $userIds);
            }

            if ($emails !== []) {
                $this->deleteWhere(MailEntity::class, 'e.emailTo IN (:ids)', $emails);
            }

            if ($usernames !== []) {
                $this->deleteWhere(UserLoginAttemptEntity::class, 'e.username IN (:ids)', $usernames);
            }
        }

        // everything else that was created, newest class first (users last)
        foreach (array_reverse($ids, true) as $class => $classIds) {
            $this->deleteWhere($class, 'e.id IN (:ids)', $classIds);
        }
    }

    /**
     * @param class-string $class
     * @param list<string> $ids
     */
    private function deleteWhere(string $class, string $where, array $ids) : void
    {
        try {
            $this->em()->createQuery('DELETE FROM ' . $class . ' e WHERE ' . $where)
                ->setParameter('ids', $ids)
                ->execute();
        } catch (Throwable $exception) {
            // still referenced by something that is not ours: leave it
            codecept_debug('CleanupTestData kept ' . $class . ': ' . $exception->getMessage());
        }
    }

    private function em() : EntityManagerDecorator
    {
        return $this->em ?? $this->grabEm();
    }

    private function grabEm() : EntityManagerDecorator
    {
        /** @var NetteDIModule $di */
        $di = $this->getModule(NetteDIModule::class);

        return $di->grabService(EntityManagerDecorator::class);
    }
}
