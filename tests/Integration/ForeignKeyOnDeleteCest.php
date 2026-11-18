<?php declare(strict_types=1);

namespace Tests\Integration;

use App\Database\EntityManagerDecorator;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\ForeignKeyConstraint\ReferentialAction;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\JoinColumnMapping;
use Doctrine\ORM\Mapping\ToOneOwningSideMapping;
use Doctrine\ORM\Tools\SchemaTool;
use Tests\Support\IntegrationTester;

/**
 * The ON DELETE behaviour of selected foreign keys: the Doctrine mapping, the schema diff
 * Doctrine computes against the database and the real database constraints must all agree
 * with the contract below. Read-only: nothing is written to the database.
 */
class ForeignKeyOnDeleteCest
{
    /** @var list<array{table: non-empty-string, column: string, referencedTable: string, onDelete: string}> */
    private array $contract;

    /** @var list<non-empty-string> */
    private array $guardedTables;

    private EntityManagerDecorator $em;

    /** @var list<ClassMetadata<object>> */
    private array $allMetadata;

    public function _before(IntegrationTester $I) : void
    {
        $this->contract = [
            // everything an account owns goes with it (UserFacade deletes a user with one DELETE)
            ['table' => 'user_activation', 'column' => 'user_id', 'referencedTable' => 'users', 'onDelete' => 'CASCADE'],
            ['table' => 'user_auto_login', 'column' => 'user_id', 'referencedTable' => 'users', 'onDelete' => 'CASCADE'],
            ['table' => 'user_email', 'column' => 'user_id', 'referencedTable' => 'users', 'onDelete' => 'CASCADE'],
            ['table' => 'user_email_change_request', 'column' => 'user_id', 'referencedTable' => 'users', 'onDelete' => 'CASCADE'],
            ['table' => 'user_password', 'column' => 'user_id', 'referencedTable' => 'users', 'onDelete' => 'CASCADE'],
            ['table' => 'user_password_request', 'column' => 'user_id', 'referencedTable' => 'users', 'onDelete' => 'CASCADE'],
        ];
        $this->guardedTables = ['user_activation', 'user_auto_login', 'user_email', 'user_email_change_request', 'user_password', 'user_password_request'];

        $this->em = $I->grabService(EntityManagerDecorator::class);
        $this->allMetadata = $this->em->getMetadataFactory()->getAllMetadata();
    }

    public function mappingHasExpectedOnDelete(IntegrationTester $I) : void
    {
        foreach ($this->contract as $fk) {
            $label = "{$fk['table']}.{$fk['column']}";
            [$meta, $fieldName] = $this->findOwningAssociation($I, $fk['table'], $fk['column']);

            // the documented lookup: getClassMetadata(<Entity>)->getAssociationMapping(<field>)
            $association = $this->em->getClassMetadata($meta->getName())->getAssociationMapping($fieldName);
            $I->assertInstanceOf(ToOneOwningSideMapping::class, $association, "$label: association is not an owning to-one side");

            $joinColumn = $this->findJoinColumn($association, $fk['column']);
            $I->assertNotNull($joinColumn, "$label: join column not found in association {$meta->getName()}::\$$fieldName");

            $targetTable = $this->em->getClassMetadata($association->targetEntity)->getTableName();
            $I->assertSame($fk['referencedTable'], $targetTable, "$label: association targets the wrong table");

            $I->assertSame($fk['onDelete'], strtoupper((string) $joinColumn->onDelete), "$label: mapped onDelete differs from the contract");

            if ($fk['onDelete'] === 'SET NULL') {
                // JoinColumn nullable defaults to true when not stated explicitly
                $nullable = $joinColumn->nullable ?? true;
                $I->assertTrue($nullable, "$label: ON DELETE SET NULL needs a nullable join column");
            }
        }
    }

    public function schemaToolProposesNoForeignKeyChange(IntegrationTester $I) : void
    {
        $schemaTool = new SchemaTool($this->em);
        $statements = $schemaTool->getUpdateSchemaSql($this->allMetadata);

        $knownFkNames = [];
        $sm = $this->em->getConnection()->createSchemaManager();
        foreach ($this->guardedTables as $table) {
            foreach ($sm->introspectTableForeignKeyConstraintsByUnquotedName($table) as $constraint) {
                $name = $constraint->getObjectName();
                if ($name !== null) {
                    $knownFkNames[] = strtolower(trim($name->toString(), '"'));
                }
            }
        }

        $tablePattern = '(?:"?public"?\.)?"?(' . implode('|', $this->guardedTables) . ')"?';
        $offending = [];
        foreach ($statements as $sql) {
            if (preg_match('/^\s*ALTER\s+TABLE\s+' . $tablePattern . '\s/i', $sql) !== 1) {
                continue;
            }

            if (preg_match('/FOREIGN\s+KEY/i', $sql) === 1) {
                $offending[] = $sql;

                continue;
            }

            if (preg_match_all('/DROP\s+CONSTRAINT\s+(?:IF\s+EXISTS\s+)?"?([A-Za-z0-9_]+)"?/i', $sql, $matches) > 0) {
                foreach ($matches[1] as $constraintName) {
                    $lower = strtolower($constraintName);
                    if (str_starts_with($lower, 'fk_') || in_array($lower, $knownFkNames, true)) {
                        $offending[] = $sql;
                        break;
                    }
                }
            }
        }

        foreach ($offending as $sql) {
            $I->comment("• Pending FK change: $sql");
        }

        $I->assertSame([], $offending, 'Doctrine would change foreign keys of ' . implode(', ', $this->guardedTables) . '.');
    }

    public function databaseHasExpectedOnDelete(IntegrationTester $I) : void
    {
        $sm = $this->em->getConnection()->createSchemaManager();

        foreach ($this->contract as $fk) {
            $label = "{$fk['table']}.{$fk['column']}";
            $constraints = $sm->introspectTableForeignKeyConstraintsByUnquotedName($fk['table']);
            $I->assertNotEmpty($constraints, "{$fk['table']}: no foreign keys found in the database (does the table exist?)");

            $found = null;
            foreach ($constraints as $constraint) {
                if ($this->referencingColumns($constraint) === [$fk['column']]) {
                    $found = $constraint;
                    break;
                }
            }

            $I->assertNotNull($found, "$label: no single-column foreign key on this column in the database");

            $referencedTable = strtolower($found->getReferencedTableName()->getUnqualifiedName()->getValue());
            $I->assertSame($fk['referencedTable'], $referencedTable, "$label: database FK references the wrong table");

            $I->assertSame(
                ReferentialAction::from($fk['onDelete']),
                $found->getOnDeleteAction(),
                "$label: database ON DELETE differs from the contract",
            );

            if ($fk['onDelete'] === 'SET NULL') {
                $nullable = null;
                foreach ($sm->introspectTableColumnsByUnquotedName($fk['table']) as $column) {
                    if (strtolower($column->getObjectName()->getIdentifier()->getValue()) === $fk['column']) {
                        $nullable = !$column->getNotnull();
                    }
                }

                $I->assertTrue($nullable, "$label: ON DELETE SET NULL needs a nullable column in the database");
            }
        }
    }

    /**
     * Finds the owning association whose entity is mapped to $table and whose join column is $column.
     *
     * @return array{0: ClassMetadata<object>, 1: string}
     */
    private function findOwningAssociation(IntegrationTester $I, string $table, string $column) : array
    {
        $hits = [];
        foreach ($this->allMetadata as $meta) {
            if ($meta->getTableName() !== $table) {
                continue;
            }

            foreach ($meta->associationMappings as $fieldName => $association) {
                if ($association instanceof ToOneOwningSideMapping && $this->findJoinColumn($association, $column) !== null) {
                    $hits[] = [$meta, $fieldName];
                }
            }
        }

        $I->assertCount(1, $hits, "$table.$column: expected exactly one Doctrine association mapped to this column");

        return $hits[0];
    }

    private function findJoinColumn(ToOneOwningSideMapping $association, string $column) : ?JoinColumnMapping
    {
        foreach ($association->joinColumns as $joinColumn) {
            if (strtolower($joinColumn->name) === $column) {
                return $joinColumn;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function referencingColumns(ForeignKeyConstraint $constraint) : array
    {
        $columns = [];
        foreach ($constraint->getReferencingColumnNames() as $name) {
            $columns[] = strtolower($name->getIdentifier()->getValue());
        }

        return $columns;
    }
}
