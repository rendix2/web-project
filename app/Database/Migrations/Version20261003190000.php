<?php declare(strict_types=1);

namespace App\Database\Migrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Project-wide rule: every record ID is a BIGINT. Widens every INTEGER/SMALLINT primary key and foreign key column and every
 * INTEGER sequence of the current schema, so the result is the same whatever the database started as: built by the migrations
 * (INTEGER) or already BIGINT (where this does nothing). PostgreSQL rewrites the (small)
 * tables and re-checks the foreign keys itself, the order does not matter (int and bigint keys are comparable). Identity
 * columns take their sequence along; plain sequences are altered separately.
 */
final class Version20261003190000 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'PostgreSQL: widen every integer primary/foreign key column and sequence to BIGINT';
    }

    public function up(Schema $schema) : void
    {
        $this->skipIf(!$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform, 'BIGINT widening is written for PostgreSQL');

        $this->addSql(<<<'SQL'
            DO $$
            DECLARE
                r record;
            BEGIN
                FOR r IN
                    SELECT DISTINCT c.table_name, c.column_name
                    FROM information_schema.columns c
                    JOIN information_schema.key_column_usage k
                        ON k.table_schema = c.table_schema AND k.table_name = c.table_name AND k.column_name = c.column_name
                    JOIN information_schema.table_constraints t
                        ON t.constraint_schema = k.constraint_schema AND t.constraint_name = k.constraint_name AND t.table_name = k.table_name
                    WHERE c.table_schema = current_schema()
                        AND t.constraint_type IN ('PRIMARY KEY', 'FOREIGN KEY')
                        AND c.data_type IN ('integer', 'smallint')
                LOOP
                    EXECUTE format('ALTER TABLE %I ALTER COLUMN %I TYPE BIGINT', r.table_name, r.column_name);
                END LOOP;

                FOR r IN
                    SELECT sequencename FROM pg_sequences
                    WHERE schemaname = current_schema() AND data_type::text IN ('integer', 'smallint')
                LOOP
                    EXECUTE format('ALTER SEQUENCE %I AS BIGINT', r.sequencename);
                END LOOP;
            END
            $$
            SQL);
    }

    public function down(Schema $schema) : void
    {
        // not reversible on purpose: narrowing would fail as soon as an ID outgrows INTEGER
        $this->throwIrreversibleMigrationException('BIGINT IDs are the project-wide rule.');
    }
}
