<?php declare(strict_types=1);

namespace Tests\Integration\Schema;

use App\Database\EntityManagerDecorator;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\ORM\Tools\SchemaValidator;
use Tests\Support\IntegrationTester;
use Tests\Support\Schema\SchemaSnapshot;

/**
 * The database built by the migrations is exactly what the entities describe: Doctrine has no change to propose
 * (`orm:schema-tool:update --dump-sql` is empty). This one comparison covers the tables, columns, types, lengths,
 * precision / scale, nullability, defaults, comments, primary keys, unique and plain indexes, foreign keys with their
 * ON DELETE and the ManyToMany join tables - in both directions (missing in the database AND extra in the database).
 * The other Cests of this folder check only what the comparison cannot see (naming, conventions, PHP types).
 */
class SchemaInSyncCest
{
    /**
     * Tables that exist in the database without an entity, on purpose.
     */
    private const array TABLES_WITHOUT_ENTITY = [
        'migrations', // doctrine/migrations bookkeeping (nettrine.migrations.table in config/ext/nettrine.neon)
    ];

    /**
     * Statements that are not a difference of the schema: index NAMES are not compared here (Doctrine generates
     * IDX_<hash> names for the indexes of the ManyToMany join tables, the migrations name them K_...); the names are
     * checked by SchemaNamingCest, the existence and the columns of every index stay compared. (One blind spot, covered by
     * SchemaKeysAndIndexesCest: DBAL invents an IDX_<hash> index for a foreign key no index covers, so a missing
     * foreign key index comes out here only as such a rename.)
     */
    private const array IGNORED_STATEMENTS = [
        '/^ALTER INDEX \S+ RENAME TO \S+$/',
    ];

    private SchemaSnapshot $schema;

    public function _before(IntegrationTester $I) : void
    {
        if (!isset($this->schema)) {
            $this->schema = new SchemaSnapshot($I->grabService(EntityManagerDecorator::class));
        }
    }

    public function mappingIsValid(IntegrationTester $I) : void
    {
        $errors = (new SchemaValidator($I->grabService(EntityManagerDecorator::class)))->validateMapping();

        $messages = [];
        foreach ($errors as $class => $issues) {
            foreach ($issues as $issue) {
                $messages[] = substr((string) strrchr('\\' . $class, '\\'), 1) . ': ' . $issue;
            }
        }

        $I->assertSame([], $messages, 'The Doctrine mapping is invalid.');
    }

    public function databaseMatchesTheEntities(IntegrationTester $I) : void
    {
        /** @var EntityManagerDecorator $em */
        $em = $I->grabService(EntityManagerDecorator::class);
        $configuration = $em->getConnection()->getConfiguration();
        $previousFilter = $configuration->getSchemaAssetsFilter();

        // SchemaTool compares the entities with every table the filter lets through: without the filter the tables
        // without an entity would come out as DROP TABLE
        $configuration->setSchemaAssetsFilter(
            fn (mixed $asset) : bool => !$this->isTableWithoutEntity($asset) && (bool) $previousFilter($asset),
        );
        try {
            $statements = (new SchemaTool($em))->getUpdateSchemaSql($this->schema->entities());
        } finally {
            $configuration->setSchemaAssetsFilter($previousFilter);
        }

        $unexpected = [];
        foreach ($statements as $statement) {
            $statement = rtrim(trim($statement), ';');
            if (!$this->matchesAny($statement, self::IGNORED_STATEMENTS)) {
                $unexpected[] = $statement;
            }
        }

        $I->assertSame([], $unexpected, 'The database differs from the entities (expected no change, got the statements Doctrine would run).');
    }

    private function isTableWithoutEntity(mixed $asset) : bool
    {
        // DBAL 4 passes the asset name, possibly qualified by the schema ("public.migrations")
        if (!is_string($asset)) {
            return false;
        }
        $parts = explode('.', strtolower($asset));

        return in_array(end($parts), self::TABLES_WITHOUT_ENTITY, true);
    }

    /**
     * @param array<string> $patterns
     */
    private function matchesAny(string $statement, array $patterns) : bool
    {
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $statement) === 1) {
                return true;
            }
        }

        return false;
    }
}
