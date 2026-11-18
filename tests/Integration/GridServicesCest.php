<?php declare(strict_types=1);

namespace Tests\Integration;

use App\Model\Entity\UserEntity;
use App\UI\Admin\Role\List\RoleDataGrid as RoleListGrid;
use App\UI\Admin\User\Edit\EmailDataGrid;
use App\UI\Admin\User\Edit\PasswordHistoryGrid;
use App\UI\Admin\User\Edit\RoleDataGrid as UserRoleGrid;
use App\UI\Admin\User\List\UserDataGrid;
use Tests\Support\IntegrationTester;

/**
 * A grid service (`*Grid`, shared in the container) builds a NEW datagrid every time create() is called. Regression (2026-11-18,
 * found by the in-process page tests): the datagrid was made once in the constructor, so the second create() in one container
 * - the second request of a test, a forward - added the columns to the first grid again: "There is already column at key [id]
 * defined" / "Please set datagrid primary key before setting datasource".
 */
class GridServicesCest
{
    public function everyCreateBuildsNewGrid(IntegrationTester $I) : void
    {
        $user = $I->em()->getRepository(UserEntity::class)->findOneByUsername('TestUsername');
        $I->assertNotNull($user);

        $grids = [
            $I->grabService(UserDataGrid::class),
            $I->grabService(RoleListGrid::class),
            $I->grabService(UserRoleGrid::class)->setUserEntity($user),
            $I->grabService(EmailDataGrid::class)->setUserEntity($user),
            $I->grabService(PasswordHistoryGrid::class)->setUserEntity($user),
        ];

        foreach ($grids as $service) {
            $first = $service->create();
            $second = $service->create();

            $I->assertNotSame($first, $second, $service::class . ' returned the same grid twice.');
            $I->assertSame(array_keys($first->getColumns()), array_keys($second->getColumns()), $service::class);
        }
    }
}
