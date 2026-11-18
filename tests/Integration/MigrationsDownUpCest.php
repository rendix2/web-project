<?php declare(strict_types=1);

namespace Tests\Integration;

use App\Database\EntityManagerDecorator;
use Codeception\Attribute\Group;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\Configuration\Connection\ExistingConnection;
use Doctrine\Migrations\Configuration\Migration\ConfigurationArray;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\Migrations\Metadata\MigrationPlanList;
use Doctrine\Migrations\MigratorConfiguration;
use Doctrine\Migrations\Version\Version;
use Tests\Support\IntegrationTester;

/**
 * down() of the migrations really reverts up() (ported from alfred 2026-11-18): inside ONE transaction (PostgreSQL has
 * transactional DDL, rolled back in _after()) the migrator reverts every migration after the last irreversible one (or after
 * CUTOFF, the first migration), migrates up to the latest again, and the database schema is then identical with the schema
 * before - and no migration throws on the way.
 *
 * The migrations themselves are the input, nothing is read beyond them (which ones call throwIrreversibleMigrationException()).
 */
#[Group('migrations')]
class MigrationsDownUpCest
{
    private const string NAMESPACE = 'App\\Database\\Migrations\\';

    /**
     * The first migration (the initial schema): everything after it is reverted and applied again.
     */
    private const string CUTOFF = 'Version20230621145918';

    private EntityManagerDecorator $em;

    public function _before(IntegrationTester $I) : void
    {
        $this->em = $I->grabService(EntityManagerDecorator::class);
    }

    public function _after() : void
    {
        $connection = $this->em->getConnection();

        while ($connection->isTransactionActive()) {
            $connection->rollBack();
        }
    }

    public function downAndUpAgainLeaveTheSchemaAsItWas(IntegrationTester $I) : void
    {
        $connection = $this->em->getConnection();
        $target = $this->lastIrreversibleOrCutoff();

        $factory = $this->factory($connection);
        $factory->getMetadataStorage()->ensureInitialized();
        $latest = $factory->getVersionAliasResolver()->resolveVersionAlias('latest');
        $I->assertGreaterThan($target, (string) $latest, 'Nothing to revert after ' . $target . '.');

        $connection->beginTransaction();
        $before = $this->schema($connection);

        $down = $factory->getMigrationPlanCalculator()->getPlanUntilVersion(new Version($target));
        $I->assertGreaterThan(0, count($down), 'No migration to revert.');
        $factory->getMigrator()->migrate($down, $this->configuration());

        // a new factory: an executed migration object is frozen, up needs fresh ones
        $upFactory = $this->factory($connection);
        $up = $upFactory->getMigrationPlanCalculator()->getPlanUntilVersion($latest);
        $I->assertSame(count($down), count($up), 'Up does not re-apply what down reverted.');
        $upFactory->getMigrator()->migrate($up, $this->configuration());

        $after = $this->schema($connection);
        $diff = $connection->createSchemaManager()
            ->createComparator()
            ->compareSchemas($before, $after);

        $I->assertTrue(
            $diff->isEmpty(),
            'The schema differs after down + up of ' . count($down) . ' migrations: '
            . implode('; ', $connection->getDatabasePlatform()->getAlterSchemaSQL($diff)),
        );

        // the rollback really undoes everything (no migration committed on its own): the next tests get the database intact
        while ($connection->isTransactionActive()) {
            $connection->rollBack();
        }
        $I->assertTrue(
            $connection->createSchemaManager()->createComparator()->compareSchemas($before, $this->schema($connection))->isEmpty(),
            'The schema is not the original one after the rollback.',
        );
        $I->assertSame(
            (string) $latest,
            (string) $this->factory($connection)->getVersionAliasResolver()->resolveVersionAlias('current'),
            'The migrations table is not back at the latest version.',
        );
    }

    /**
     * A fresh factory per run (an executed migration object is frozen) with the settings of config/ext/nettrine.neon
     * (table migrations, column version).
     */
    private function factory(Connection $connection) : DependencyFactory
    {
        return DependencyFactory::fromConnection(
            new ConfigurationArray([
                'table_storage' => [
                    'table_name' => 'migrations',
                    'version_column_name' => 'version',
                ],
                'migrations_paths' => [
                    rtrim(self::NAMESPACE, '\\') => codecept_root_dir() . 'app/Database/Migrations',
                ],
            ]),
            new ExistingConnection($connection),
        );
    }

    private function configuration() : MigratorConfiguration
    {
        return (new MigratorConfiguration())
            ->setAllOrNothing(false)
            ->setTimeAllQueries(false);
    }

    private function schema(Connection $connection) : Schema
    {
        return $connection->createSchemaManager()->introspectSchema();
    }

    /**
     * The newest migration at or after CUTOFF whose down() throws IrreversibleMigration (down to it the plan can go, not further),
     * or CUTOFF itself.
     */
    private function lastIrreversibleOrCutoff() : string
    {
        $target = self::CUTOFF;

        $files = glob(codecept_root_dir() . 'app/Database/Migrations/Version*.php');

        foreach ($files === false ? [] : $files as $file) {
            $name = basename($file, '.php');
            $source = (string) file_get_contents($file);

            if ($name >= $target && str_contains($source, 'throwIrreversibleMigrationException')) {
                $target = $name;
            }
        }

        return self::NAMESPACE . $target;
    }
}
