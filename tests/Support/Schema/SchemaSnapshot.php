<?php declare(strict_types=1);

namespace Tests\Support\Schema;

use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Table;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use ReflectionClass;

/**
 * One read of the Doctrine mapping and of the database schema, shared by all tests of one Cest (Codeception creates the
 * Cest object once per class: the Cest keeps the snapshot in a property and builds it in the first `_before()`).
 *
 * Holds only plain values and detached DBAL schema objects - nothing that needs the entity manager of the test that built it
 * (the Integration suite has a new DI container for every test).
 */
class SchemaSnapshot
{
    /** @var list<ClassMetadata<object>> */
    private array $entities;

    /** @var array<string, Table> by table name */
    private array $tables;

    /** @var array<string, list<Index>> by table name: the indexes that really exist in the database */
    private array $indexes;

    /** @var array<string, string> "table.column" => the native PostgreSQL type (`information_schema.columns.udt_name`) */
    private array $nativeTypes;

    public function __construct(EntityManagerInterface $em)
    {
        $this->entities = [];
        foreach ($em->getMetadataFactory()->getAllMetadata() as $meta) {
            if (!$meta->isMappedSuperclass && !$meta->isEmbeddedClass) {
                $this->entities[] = $meta;
            }
        }

        $connection = $em->getConnection();

        $schemaManager = $connection->createSchemaManager();
        $this->tables = [];
        $this->indexes = [];
        foreach ($schemaManager->introspectTables() as $table) {
            $name = $this->tableName($table);
            $this->tables[$name] = $table;
            // not $table->getIndexes(): a DBAL Table invents an index (IDX_<hash>) for every foreign key no index covers
            $this->indexes[$name] = $schemaManager->introspectTableIndexes($table->getObjectName());
        }

        ksort($this->tables);

        // the catalogue is read on purpose (AGENTS.md: the QueryBuilder rule exempts tests of the database catalogue)
        $rows = $connection->createQueryBuilder()
            ->select('table_name', 'column_name', 'udt_name')
            ->from('information_schema.columns')
            ->where('table_schema = current_schema()')
            ->fetchAllAssociative();

        $this->nativeTypes = [];
        foreach ($rows as $row) {
            $this->nativeTypes[$this->text($row['table_name']) . '.' . $this->text($row['column_name'])] = $this->text($row['udt_name']);
        }
    }

    /**
     * Every mapped entity (no mapped superclasses, no embeddables).
     *
     * @return list<ClassMetadata<object>>
     */
    public function entities() : array
    {
        return $this->entities;
    }

    /**
     * Every table of the database (the current schema), by name.
     *
     * @return array<string, Table>
     */
    public function tables() : array
    {
        return $this->tables;
    }

    /**
     * The indexes of the table as the database has them (the primary key included, named "primary").
     *
     * @return list<Index>
     */
    public function indexes(string $table) : array
    {
        return $this->indexes[$table] ?? [];
    }

    public function nativeType(string $table, string $column) : ?string
    {
        return $this->nativeTypes[$table . '.' . $column] ?? null;
    }

    /**
     * Every column of the database (the current schema, tables without an entity included) with its native type.
     *
     * @return array<string, string> "table.column" => `information_schema.columns.udt_name`
     */
    public function nativeTypes() : array
    {
        return $this->nativeTypes;
    }

    public function tableName(Table $table) : string
    {
        return $table->getObjectName()->getUnqualifiedName()->getValue();
    }

    /**
     * @return list<string>
     */
    public function columnNames(Table $table) : array
    {
        $names = [];
        foreach ($table->getColumns() as $column) {
            $names[] = $column->getObjectName()->getIdentifier()->getValue();
        }

        return $names;
    }

    public function indexName(Index $index) : string
    {
        return $index->getObjectName()->getIdentifier()->getValue();
    }

    /**
     * @return list<string> in the order of the index
     */
    public function indexColumns(Index $index) : array
    {
        $names = [];
        foreach ($index->getIndexedColumns() as $column) {
            $names[] = $column->getColumnName()->getIdentifier()->getValue();
        }

        return $names;
    }

    /**
     * @return list<string> in the order of the key, empty when the table has none
     */
    public function primaryKeyColumns(Table $table) : array
    {
        $names = [];
        foreach ($table->getPrimaryKeyConstraint()?->getColumnNames() ?? [] as $name) {
            $names[] = $name->getIdentifier()->getValue();
        }

        return $names;
    }

    public function primaryKeyName(Table $table) : ?string
    {
        return $table->getPrimaryKeyConstraint()?->getObjectName()?->getIdentifier()->getValue();
    }

    public function foreignKeyName(ForeignKeyConstraint $foreignKey) : string
    {
        return $foreignKey->getObjectName()?->getIdentifier()->getValue() ?? '(unnamed)';
    }

    /**
     * @return list<string> in the order of the key
     */
    public function foreignKeyColumns(ForeignKeyConstraint $foreignKey) : array
    {
        $names = [];
        foreach ($foreignKey->getReferencingColumnNames() as $name) {
            $names[] = $name->getIdentifier()->getValue();
        }

        return $names;
    }

    /**
     * The subject of a message: `ChatEntity::$aiLimit (chat.ai_limit)`, `ChatEntity::$members` (no column of its own).
     *
     * @param ClassMetadata<object> $meta
     */
    public function label(ClassMetadata $meta, string $property, ?string $column = null) : string
    {
        $label = (new ReflectionClass($meta->getName()))->getShortName() . '::$' . $property;

        return $column === null ? $label : $label . ' (' . $meta->getTableName() . '.' . $column . ')';
    }

    private function text(mixed $value) : string
    {
        return is_scalar($value) ? (string) $value : '';
    }
}
