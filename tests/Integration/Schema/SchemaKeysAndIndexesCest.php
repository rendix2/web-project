<?php declare(strict_types=1);

namespace Tests\Integration\Schema;

use App\Database\EntityManagerDecorator;
use Tests\Support\IntegrationTester;
use Tests\Support\Schema\SchemaSnapshot;

/**
 * Keys beyond what the schema comparison (SchemaInSyncCest) sees. Doctrine is content with an index that merely CONTAINS
 * the foreign key column; PostgreSQL uses an index for the lookups of a foreign key (the joins, ON DELETE of the parent row)
 * only when the key is its LEADING part. Every table of the database, the ManyToMany join tables included.
 *
 * BIGINT identifiers (mapping, primary / foreign key columns, sequences) are guarded by Integration/BigIntIdentifiersCest.
 */
class SchemaKeysAndIndexesCest
{
    private SchemaSnapshot $schema;

    public function _before(IntegrationTester $I) : void
    {
        if (!isset($this->schema)) {
            $this->schema = new SchemaSnapshot($I->grabService(EntityManagerDecorator::class));
        }
    }

    public function everyForeignKeyLeadsAnIndex(IntegrationTester $I) : void
    {
        $missing = [];
        foreach ($this->schema->tables() as $tableName => $table) {
            $leadingColumns = [$this->schema->primaryKeyColumns($table)];
            foreach ($this->schema->indexes($tableName) as $index) {
                $leadingColumns[] = $this->schema->indexColumns($index);
            }

            foreach ($table->getForeignKeys() as $foreignKey) {
                $columns = $this->schema->foreignKeyColumns($foreignKey);
                $covered = false;
                foreach ($leadingColumns as $indexColumns) {
                    if (array_slice($indexColumns, 0, count($columns)) === $columns) {
                        $covered = true;
                        break;
                    }
                }

                if (!$covered) {
                    $missing[] = $tableName . '.' . implode(', ', $columns) . ' (' . $this->schema->foreignKeyName($foreignKey)
                        . '): expected an index starting with (' . implode(', ', $columns) . '), got none';
                }
            }
        }

        $I->assertSame([], $missing, 'Foreign keys without an index that starts with their columns.');
    }
}
