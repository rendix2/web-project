<?php declare(strict_types=1);

namespace Tests\Integration\Schema;

use App\Database\EntityManagerDecorator;
use Doctrine\DBAL\Schema\Index\IndexType;
use Tests\Support\IntegrationTester;
use Tests\Support\Schema\SchemaSnapshot;

/**
 * Names the schema comparison (SchemaInSyncCest) does not judge: the database is snake_case, the entity properties are
 * camelCase, indexes and foreign keys carry the prefix of their kind (AGENTS.md "Names of indexes and foreign keys":
 * K_ index, UK_ unique index, FK_ foreign key - the committed migrations use one or two underscores after the prefix).
 * Covers every table of the database, the ManyToMany join tables and the tables without an entity included.
 */
class SchemaNamingCest
{
    private const string SNAKE_CASE = '/^[a-z][a-z0-9]*(_[a-z0-9]+)*$/';

    private const string CAMEL_CASE = '/^[a-z][A-Za-z0-9]*$/';

    private SchemaSnapshot $schema;

    public function _before(IntegrationTester $I) : void
    {
        if (!isset($this->schema)) {
            $this->schema = new SchemaSnapshot($I->grabService(EntityManagerDecorator::class));
        }
    }

    public function tableAndColumnNamesAreSnakeCase(IntegrationTester $I) : void
    {
        $wrong = [];
        foreach ($this->schema->tables() as $tableName => $table) {
            if (preg_match(self::SNAKE_CASE, $tableName) !== 1) {
                $wrong[] = "$tableName: expected a snake_case table name, got \"$tableName\"";
            }
            foreach ($this->schema->columnNames($table) as $column) {
                if (preg_match(self::SNAKE_CASE, $column) !== 1) {
                    $wrong[] = "$tableName.$column: expected a snake_case column name, got \"$column\"";
                }
            }
        }

        $I->assertSame([], $wrong, 'Table or column names are not snake_case.');
    }

    public function entityPropertiesAreCamelCase(IntegrationTester $I) : void
    {
        $wrong = [];
        foreach ($this->schema->entities() as $meta) {
            foreach ([...$meta->getFieldNames(), ...$meta->getAssociationNames()] as $property) {
                if (preg_match(self::CAMEL_CASE, $property) !== 1) {
                    $wrong[] = $this->schema->label($meta, $property) . ": expected a camelCase property name, got \"$property\"";
                }
            }
        }

        $I->assertSame([], $wrong, 'Entity properties are not camelCase.');
    }

    public function indexAndForeignKeyNamesCarryTheirPrefix(IntegrationTester $I) : void
    {
        $wrong = [];
        foreach ($this->schema->tables() as $tableName => $table) {
            $names = [];
            // DBAL still lists the primary key among the indexes too, as "primary"
            $primaryKey = [$this->schema->primaryKeyName($table), 'primary'];
            foreach ($this->schema->indexes($tableName) as $index) {
                $name = $this->schema->indexName($index);
                if (!in_array($name, $primaryKey, true)) {
                    $names[$name] = $index->getType() === IndexType::UNIQUE ? 'uk_' : 'k_';
                }
            }
            foreach ($table->getForeignKeys() as $foreignKey) {
                $names[$this->schema->foreignKeyName($foreignKey)] = 'fk_';
            }

            foreach ($names as $name => $prefix) {
                if (str_starts_with(strtolower($name), $prefix)) {
                    continue;
                }
                $wrong[] = "$tableName.$name: expected the prefix " . strtoupper($prefix) . ", got \"$name\"";
            }
        }

        $I->assertSame([], $wrong, 'Index or foreign key names without the prefix of their kind.');
    }
}
