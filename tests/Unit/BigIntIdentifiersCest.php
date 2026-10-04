<?php declare(strict_types=1);

namespace Tests\Unit;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use App\Database\EntityManagerDecorator;
use Tests\Support\UnitTester;

/**
 * Project-wide rule: every numeric record ID is a BIGINT (entities, primary keys, foreign keys, sequences).
 * Fresh databases are built by the migrations (INTEGER first, a later migration widens it); databases that already
 * have BIGINT keep them - the migration is a no-op there; this test guards both.
 */
class BigIntIdentifiersCest
{
    public function everyNumericEntityIdentifierIsMappedAsBigInt(UnitTester $I) : void
    {
        /** @var EntityManagerDecorator $em */
        $em = $I->grabService(EntityManagerDecorator::class);

        $wrong = [];

        foreach ($em->getMetadataFactory()->getAllMetadata() as $metadata) {
            foreach ($metadata->getIdentifierFieldNames() as $field) {
                $type = $metadata->getTypeOfField($field);

                if (in_array($type, [Types::INTEGER, Types::SMALLINT], true)) {
                    $wrong[] = $metadata->getName() . '::$' . $field . ' (' . $type . ')';
                }
            }
        }

        $I->assertSame([], $wrong, 'These identifiers are not BIGINT.');
    }

    public function everyKeyColumnAndSequenceInTheDatabaseIsBigInt(UnitTester $I) : void
    {
        /** @var Connection $connection */
        $connection = $I->grabService(Connection::class);

        $columns = $connection->fetchFirstColumn(<<<'SQL'
            SELECT DISTINCT c.table_name || '.' || c.column_name || ' (' || c.data_type || ')'
            FROM information_schema.columns c
            JOIN information_schema.key_column_usage k
                ON k.table_schema = c.table_schema AND k.table_name = c.table_name AND k.column_name = c.column_name
            JOIN information_schema.table_constraints t
                ON t.constraint_schema = k.constraint_schema AND t.constraint_name = k.constraint_name AND t.table_name = k.table_name
            WHERE c.table_schema = current_schema()
                AND t.constraint_type IN ('PRIMARY KEY', 'FOREIGN KEY')
                AND c.data_type IN ('integer', 'smallint')
            ORDER BY 1
            SQL);

        $sequences = $connection->fetchFirstColumn(
            "SELECT sequencename || ' (' || data_type::text || ')' FROM pg_sequences WHERE schemaname = current_schema() AND data_type::text IN ('integer', 'smallint') ORDER BY 1",
        );

        $I->assertSame([], $columns, 'Primary/foreign key columns that are not BIGINT.');
        $I->assertSame([], $sequences, 'Sequences that are not BIGINT.');
    }
}
