<?php declare(strict_types=1);

namespace Tests\Integration\Schema;

use App\Database\EntityManagerDecorator;
use App\Database\Types\IpAddressType;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\ClassMetadata;
use Ramsey\Uuid\Doctrine\UuidType;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;
use Tests\Support\IntegrationTester;
use Tests\Support\Schema\SchemaSnapshot;

/**
 * Types the schema comparison (SchemaInSyncCest) cannot judge. The comparison checks that the column is what the
 * Doctrine type DECLARES; here the declaration itself is checked (an IP address is a real INET, not a string that happens
 * to match a VARCHAR declaration) and the PHP side of the mapping (nullability, BIGINT read as int).
 */
class SchemaTypesCest
{
    /**
     * Doctrine type => the native PostgreSQL types (`information_schema.columns.udt_name`) its column may have.
     */
    private const array NATIVE_TYPES = [
        IpAddressType::NAME => ['inet'],
        UuidType::NAME => ['uuid'],
        Types::GUID => ['uuid'],
        Types::DECIMAL => ['numeric'],
        Types::JSON => ['json', 'jsonb'],
    ];

    private SchemaSnapshot $schema;

    public function _before(IntegrationTester $I) : void
    {
        if (!isset($this->schema)) {
            $this->schema = new SchemaSnapshot($I->grabService(EntityManagerDecorator::class));
        }
    }

    public function customTypesUseNativeColumnTypes(IntegrationTester $I) : void
    {
        $wrong = [];
        foreach ($this->schema->entities() as $meta) {
            foreach ($meta->fieldMappings as $field => $mapping) {
                $expected = self::NATIVE_TYPES[$mapping->type] ?? null;
                // a missing column is reported by SchemaInSyncCest
                $actual = $this->schema->nativeType($meta->getTableName(), $mapping->columnName);
                if ($expected === null || $actual === null || in_array($actual, $expected, true)) {
                    continue;
                }

                $wrong[] = $this->schema->label($meta, $field, $mapping->columnName)
                    . ': expected ' . implode(' or ', $expected) . " ({$mapping->type}), got $actual";
            }
        }

        $I->assertSame([], $wrong, 'Columns of these Doctrine types do not have the native database type.');
    }

    /**
     * DBAL 4 returns BIGINT as int when it fits PHP_INT_MAX (always on 64 bits), so the property is int, not string.
     */
    public function bigIntPropertiesAreInt(IntegrationTester $I) : void
    {
        $wrong = [];
        foreach ($this->schema->entities() as $meta) {
            foreach ($meta->fieldMappings as $field => $mapping) {
                if ($mapping->type !== Types::BIGINT) {
                    continue;
                }

                $type = $this->property($meta, $field)->getType();
                if (!$type instanceof ReflectionNamedType || $type->getName() !== 'int') {
                    $wrong[] = $this->schema->label($meta, $field, $mapping->columnName)
                        . ': expected PHP type int (bigint), got ' . ($type === null ? 'no type' : (string) $type);
                }
            }
        }

        $I->assertSame([], $wrong, 'BIGINT columns not read into an int property.');
    }

    /**
     * A NULL from the database must fit the property, and a property that may be null must have a nullable column.
     * Columns and the join columns of the owning to-one associations; the nullability of the column itself is compared
     * with the mapping by SchemaInSyncCest.
     */
    public function phpNullabilityMatchesTheMapping(IntegrationTester $I) : void
    {
        $wrong = [];
        foreach ($this->schema->entities() as $meta) {
            foreach ($meta->fieldMappings as $field => $mapping) {
                $type = $this->property($meta, $field)->getType();
                // a blob is a stream resource in PHP (no type exists for it), such properties are untyped on purpose
                if ($type === null && $mapping->type === Types::BLOB) {
                    continue;
                }

                $problem = $this->nullabilityProblem($mapping->nullable ?? false, $type?->allowsNull(), (string) $type);
                if ($problem !== null) {
                    $wrong[] = $this->schema->label($meta, $field, $mapping->columnName) . ': ' . $problem;
                }
            }

            foreach ($meta->associationMappings as $field => $association) {
                if (!$association->isToOneOwningSide()) {
                    continue;
                }

                $nullable = false;
                $columns = [];
                foreach ($association->joinColumns as $joinColumn) {
                    // #[JoinColumn] is nullable unless it says otherwise
                    $nullable = $nullable || ($joinColumn->nullable ?? true);
                    $columns[] = $joinColumn->name;
                }

                $type = $this->property($meta, $field)->getType();
                $problem = $this->nullabilityProblem($nullable, $type?->allowsNull(), (string) $type);
                if ($problem !== null) {
                    $wrong[] = $this->schema->label($meta, $field, implode(', ', $columns)) . ': ' . $problem;
                }
            }
        }

        $I->assertSame([], $wrong, 'The PHP type of a property does not match the nullability of its mapping.');
    }

    private function nullabilityProblem(bool $mappedNullable, ?bool $phpNullable, string $phpType) : ?string
    {
        if ($phpNullable === null) {
            return 'expected a typed property, got no type';
        }

        if ($mappedNullable === $phpNullable) {
            return null;
        }

        return $mappedNullable
            ? "expected a nullable PHP type (mapping nullable: true), got $phpType"
            : "expected a non-nullable PHP type (mapping nullable: false), got $phpType";
    }

    /**
     * @param ClassMetadata<object> $meta
     */
    private function property(ClassMetadata $meta, string $property) : ReflectionProperty
    {
        return (new ReflectionClass($meta->getName()))->getProperty($property);
    }
}
