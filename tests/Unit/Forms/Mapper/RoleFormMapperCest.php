<?php declare(strict_types=1);

namespace Tests\Unit\Forms\Mapper;

use App\Forms\Mapper\RoleFormMapper;
use App\Model\Entity\RoleEntity;
use App\UI\Admin\Role\Create\CreateRoleValues;
use App\UI\Admin\Role\Edit\EditRoleValues;
use Tests\Support\Unit\DefaultsContainer;
use Tests\Support\UnitTester;

/**
 * The admin role forms <-> RoleEntity. No database, no form: a recording container and plain entities.
 */
class RoleFormMapperCest
{
    public function aRoleIsFilled(UnitTester $I) : void
    {
        $role = new RoleEntity();
        $role->name = 'Admin';

        $container = new DefaultsContainer();
        (new RoleFormMapper())->fill($container, $role);

        $I->assertSame(['name' => 'Admin'], $container->defaults);
    }

    public function anEditWritesTheNameOntoTheRole(UnitTester $I) : void
    {
        $role = new RoleEntity();
        $role->name = 'Old';
        $role->description = 'kept';

        $values = new EditRoleValues();
        $values->name = 'New';

        $I->assertSame($role, (new RoleFormMapper())->apply($values, $role));
        $I->assertSame('New', $role->name);
        $I->assertSame('kept', $role->description, 'The form has no description: it is left alone.');
    }

    public function aCreateBuildsANewRoleWithAnEmptyDescription(UnitTester $I) : void
    {
        $values = new CreateRoleValues();
        $values->name = 'Editor';

        $role = (new RoleFormMapper())->apply($values);

        $I->assertSame('Editor', $role->name);
        // the column is NOT NULL and the form has no description: the create form used to fail on it
        $I->assertSame('', $role->description);
    }
}
