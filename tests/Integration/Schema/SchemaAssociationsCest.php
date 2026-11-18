<?php declare(strict_types=1);

namespace Tests\Integration\Schema;

use App\Database\EntityManagerDecorator;
use Doctrine\ORM\Mapping\JoinColumn;
use ReflectionClass;
use Tests\Support\IntegrationTester;
use Tests\Support\Schema\SchemaSnapshot;

/**
 * Project conventions for the ManyToOne associations; the schema they produce (join column, foreign key, ON DELETE)
 * is compared by SchemaInSyncCest, the PHP nullability by SchemaTypesCest.
 */
class SchemaAssociationsCest
{
    private SchemaSnapshot $schema;

    public function _before(IntegrationTester $I) : void
    {
        if (!isset($this->schema)) {
            $this->schema = new SchemaSnapshot($I->grabService(EntityManagerDecorator::class));
        }
    }

    /**
     * Without #[JoinColumn] Doctrine silently uses its defaults (nullable, no ON DELETE): the attribute makes the choice visible.
     */
    public function manyToOneDeclaresItsJoinColumn(IntegrationTester $I) : void
    {
        $missing = [];
        foreach ($this->schema->entities() as $meta) {
            $class = new ReflectionClass($meta->getName());
            foreach ($meta->associationMappings as $field => $association) {
                if ($association->isManyToOne() && $class->getProperty($field)->getAttributes(JoinColumn::class) === []) {
                    $missing[] = $this->schema->label($meta, $field) . ': expected #[JoinColumn] on the #[ManyToOne], got none';
                }
            }
        }

        $I->assertSame([], $missing, 'ManyToOne associations without #[JoinColumn].');
    }

    public function manyToOneCascadesPersist(IntegrationTester $I) : void
    {
        $missing = [];
        foreach ($this->schema->entities() as $meta) {
            foreach ($meta->associationMappings as $field => $association) {
                if ($association->isManyToOne() && !$association->isCascadePersist()) {
                    $missing[] = $this->schema->label($meta, $field) . ": expected cascade ['persist'], got ["
                        . implode(', ', $association->cascade) . ']';
                }
            }
        }

        $I->assertSame([], $missing, 'ManyToOne associations without cascade persist.');
    }
}
