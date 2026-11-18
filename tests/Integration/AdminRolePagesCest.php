<?php declare(strict_types=1);

namespace Tests\Integration;

use App\Model\Entity\RoleEntity;
use Tests\Support\Grid\DatagridPage;
use Tests\Support\IntegrationTester;

/**
 * The administration of the roles (the list with its grid, create, edit) run through the Nette Application IN the test process, so
 * `make coverage` measures the presenters, the forms and the grid. Over real HTTP: tests/Functional (AdminFormMappingCest,
 * UserFormsCest, GridExerciserCest, ...).
 */
class AdminRolePagesCest
{
    private const string LIST = '/admin.role.list/';

    public function theListFiltersAndSortsTheRoles(IntegrationTester $I) : void
    {
        $role = $this->createRole($I);
        $I->loginAsAdmin();

        $I->amOnPage(self::LIST . '?' . http_build_query(['grid-filter' => ['name' => strtolower($role->name)]]));
        $I->seeResponseCodeIs(200);
        $I->assertSame([(string) $role->id], $this->rowIds($I));

        $I->amOnPage(self::LIST . '?' . http_build_query(['grid-filter' => ['id' => (string) $role->id]]));
        $I->assertSame([(string) $role->id], $this->rowIds($I));

        $I->amOnPage(self::LIST . '?' . http_build_query(['grid-sort' => ['name' => 'ASC'], 'grid-perPage' => 100]));
        $ascending = $this->rowIds($I);
        $I->amOnPage(self::LIST . '?' . http_build_query(['grid-sort' => ['name' => 'DESC'], 'grid-perPage' => 100]));
        $I->assertSame(array_reverse($ascending), $this->rowIds($I));
    }

    public function theInlineEditRenamesTheRole(IntegrationTester $I) : void
    {
        $role = $this->createRole($I);
        $newName = $role->name . 'X';
        $I->loginAsAdmin();
        $I->amOnPage(self::LIST);

        $I->haveHttpHeader('Sec-Fetch-Site', 'same-origin');
        $I->haveHttpHeader('X-Requested-With', 'XMLHttpRequest');
        $I->sendAjaxPostRequest(self::LIST . '?do=grid-filter-submit', [
            'inline_edit' => ['_id' => (string) $role->id, '_primary_where_column' => 'id', 'name' => $newName, 'submit' => 'x'],
            'perPage' => '20',
        ]);
        $I->seeResponseCodeIs(200);

        $I->assertSame($newName, $I->reload(RoleEntity::class, $role->id)?->name);
    }

    public function aRoleCanBeDeleted(IntegrationTester $I) : void
    {
        $role = $this->createRole($I);
        $roleId = $role->id;
        $I->loginAsAdmin();

        $I->amOnPage(self::LIST . '?' . http_build_query(['grid-__id' => $roleId, 'grid-__key' => 'delete', 'do' => 'grid-actionCallback']));
        $I->seeResponseCodeIs(200);

        $I->assertNull($I->reload(RoleEntity::class, $roleId));
    }

    public function theCreateAndTheEditFormSaveTheRole(IntegrationTester $I) : void
    {
        $name = 'InRole' . random_int(100000, 999999);
        $I->loginAsAdmin();

        $I->amOnPage('/admin.role.create/');
        $I->seeResponseCodeIs(200);
        $I->submitForm('#frm-createForm', ['name' => $name]);
        $I->seeResponseCodeIs(200);

        $em = $I->em();
        $em->clear();
        $role = $em->getRepository(RoleEntity::class)->findOneBy(['name' => $name]);
        $I->assertNotNull($role, 'The role was not created.');

        $I->amOnPage('/admin.role.edit/default/' . $role->id);
        $I->seeResponseCodeIs(200);
        $I->assertSame($name, $I->grabValueFrom('#frm-editForm-name'));

        $I->submitForm('#frm-editForm', ['name' => $name . 'Renamed']);
        $I->seeResponseCodeIs(200);
        $I->assertSame($name . 'Renamed', $I->reload(RoleEntity::class, $role->id)?->name);

        $I->seePageIsRefused('/admin.role.edit/default/999999999', 404);
    }

    /**
     * @return list<string>
     */
    private function rowIds(IntegrationTester $I) : array
    {
        return (new DatagridPage($I->grabPageSource()))->rowIds('grid');
    }

    private function createRole(IntegrationTester $I) : RoleEntity
    {
        $role = new RoleEntity();
        $role->name = 'InRole' . random_int(100000, 999999);
        $role->description = 'test';

        $I->em()->persist($role);
        $I->em()->flush();

        return $role;
    }
}
