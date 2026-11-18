<?php declare(strict_types=1);

namespace Tests\Integration;

use App\Core\AutoLoginTokenService;
use App\Model\Entity\RoleEntity;
use App\Model\Entity\UserAutoLoginEntity;
use App\Model\Entity\UserEmailEntity;
use App\Model\Entity\UserEntity;
use App\Model\Entity\UserPasswordEntity;
use Nette\Security\Passwords;
use Tests\Support\Grid\DatagridPage;
use Tests\Support\IntegrationTester;

/**
 * The administration of the users (the list with its grid, create, edit with its three grids) run through the Nette Application IN
 * the test process, so `make coverage` measures the presenters, the forms and the grids. Over real HTTP: tests/Functional
 * (AdminFormMappingCest, UserFormsCest, GridExerciserCest, GridRowPermissionsCest, ...).
 */
class AdminUserPagesCest
{
    private const string LIST = '/admin.user.list/';

    public function theAdministrationNeedsAnAdmin(IntegrationTester $I) : void
    {
        $I->seePageIsRefused(self::LIST, 401);

        $user = $I->createUser('inplain', true, 'Normal');
        $I->login($user->username, IntegrationTester::PASSWORD);

        $I->seePageIsRefused(self::LIST, 403);
        $I->seePageIsRefused('/admin.user.create/', 403);
    }

    public function theAdministrationLogsInByTheStayLoggedInCookie(IntegrationTester $I) : void
    {
        $admin = $I->em()->getRepository(UserEntity::class)->findOneByUsername('TestUsername');
        $I->assertNotNull($admin);
        $token = $I->grabService(AutoLoginTokenService::class)->issue($admin, '127.0.0.1', 'Local', null);
        $I->setCookie('autoLogin', $token);

        $I->amOnPage(self::LIST);
        $I->seeResponseCodeIs(200);

        // the record of the token (persisted by the test, so removed by CleanupTestData) got a new token
        $I->assertSame(0, $I->em()->getRepository(UserAutoLoginEntity::class)->count(['tokenHash' => hash('sha256', $token)]), 'The used token was not rotated.');
    }

    public function theListFiltersAndSortsTheUsers(IntegrationTester $I) : void
    {
        $user = $I->createUser('inlist');
        $I->loginAsAdmin();

        $I->amOnPage(self::LIST);
        $I->seeResponseCodeIs(200);
        $I->assertContains('grid', (new DatagridPage($I->grabPageSource()))->gridNames());

        foreach (['username' => $user->username, 'email' => $user->email, 'fullName' => 'In Process', 'id' => (string) $user->id] as $column => $value) {
            $I->amOnPage(self::LIST . '?' . http_build_query(['grid-filter' => [$column => $value], 'grid-perPage' => 100]));
            $I->seeResponseCodeIs(200);
            $I->assertContains((string) $user->uuid, $this->rowIds($I), 'The filter ' . $column . ' does not find the user.');
        }

        $I->amOnPage(self::LIST . '?' . http_build_query(['grid-filter' => ['id' => 'abc']]));
        $I->assertSame([], $this->rowIds($I), 'A malformed id matched something.');

        $I->amOnPage(self::LIST . '?' . http_build_query(['grid-filter' => ['isActive' => '1', 'username' => $user->username]]));
        $I->assertSame([(string) $user->uuid], $this->rowIds($I));

        $ascending = $this->sortedIds($I, 'ASC');
        $descending = $this->sortedIds($I, 'DESC');
        $I->assertNotSame([], $ascending);
        $I->assertNotSame($ascending, $descending, 'Sorting by the full name does nothing.');
    }

    public function theInlineEditSavesTheUser(IntegrationTester $I) : void
    {
        $user = $I->createUser('ininline');
        $I->grabService(AutoLoginTokenService::class)->issue($user, '127.0.0.1', 'Local', null);
        $I->loginAsAdmin();
        $I->amOnPage(self::LIST);

        $newEmail = 'inline.' . strtolower($user->username) . '@example.com';

        $this->inlineEdit($I, $user, 'NoSpace', $user->email, '1');
        $saved = $I->reload(UserEntity::class, $user->id);
        $I->assertNotNull($saved);
        $I->assertSame('In', $saved->name, 'A full name without a space was saved.');

        $this->inlineEdit($I, $user, 'Grid Renamed', $newEmail, '0');
        $saved = $I->reload(UserEntity::class, $user->id);
        $I->assertNotNull($saved);
        $I->assertSame('Grid', $saved->name);
        $I->assertSame('Renamed', $saved->surname);
        $I->assertSame($newEmail, $saved->email);
        $I->assertFalse($saved->isActive);
        $I->assertSame(1, $I->em()->getRepository(UserEmailEntity::class)->count(['user' => $user->id, 'email' => $newEmail]));
        $I->assertSame(0, $I->em()->getRepository(UserAutoLoginEntity::class)->count(['user' => $user->id]), 'A deactivated user kept his auto logins.');
    }

    public function anotherUserCanBeDeletedButNotTheAdminHimself(IntegrationTester $I) : void
    {
        $user = $I->createUser('indelete');
        $I->loginAsAdmin();

        $admin = $I->em()->getRepository(UserEntity::class)->findOneByUsername('TestUsername');
        $I->assertNotNull($admin);

        $I->seePageIsRefused($this->deleteUrl((string) $admin->uuid), 403);

        $userId = $user->id;
        $I->amOnPage($this->deleteUrl((string) $user->uuid));
        $I->seeResponseCodeIs(200);
        $I->assertNull($I->reload(UserEntity::class, $userId), 'The user was not deleted.');
        $I->assertNotNull($I->reload(UserEntity::class, $admin->id));
    }

    public function theCreateFormCreatesTheUser(IntegrationTester $I) : void
    {
        $tag = 'increate' . random_int(100000, 999999);
        $I->loginAsAdmin();

        $I->amOnPage('/admin.user.create/');
        $I->seeResponseCodeIs(200);
        $I->submitForm('#frm-createForm', [
            'name' => 'Created',
            'surname' => 'Person',
            'username' => $tag,
            'email' => $tag . '@example.com',
            'password' => IntegrationTester::PASSWORD,
            'password2' => IntegrationTester::PASSWORD,
            'isActive' => '1',
        ]);
        $I->seeResponseCodeIs(200);

        $em = $I->em();
        $em->clear();
        $created = $em->getRepository(UserEntity::class)->findOneByUsername($tag);
        $I->assertNotNull($created, 'The user was not created.');
        $I->assertTrue($created->isActive);
        $I->assertTrue($I->grabService(Passwords::class)->verify(IntegrationTester::PASSWORD, $created->password));
        $I->assertSame(1, $em->getRepository(UserPasswordEntity::class)->count(['user' => $created->id]));
        $I->assertSame(1, $em->getRepository(UserEmailEntity::class)->count(['user' => $created->id]));

        $I->amOnPage('/admin.user.create/');
        $I->submitForm('#frm-createForm', [
            'name' => 'Created',
            'surname' => 'Twice',
            'username' => $tag,
            'email' => $tag . '@example.com',
            'password' => IntegrationTester::PASSWORD,
            'password2' => IntegrationTester::PASSWORD,
        ]);
        $I->see('už existuje');
        $I->assertSame(1, $I->em()->getRepository(UserEntity::class)->count(['username' => $tag]));
    }

    public function theEditFormShowsAndSavesTheUser(IntegrationTester $I) : void
    {
        $user = $I->createUser('inedit');
        $I->loginAsAdmin();
        $url = '/admin.user.edit/default/' . $user->uuid;

        $I->amOnPage($url);
        $I->seeResponseCodeIs(200);
        $I->assertSame($user->username, $I->grabValueFrom('#frm-editForm-username'));
        $I->seeCheckboxIsChecked('#frm-editForm-isActive');

        $grids = (new DatagridPage($I->grabPageSource()))->gridNames();
        foreach (['rolesGrid', 'emailsGrid', 'passwordHistoryGrid'] as $grid) {
            $I->assertContains($grid, $grids);
        }

        $newEmail = 'edited.' . strtolower($user->username) . '@example.com';
        $I->uncheckOption('#frm-editForm-isActive');
        $I->submitForm('#frm-editForm', [
            'name' => 'Edited',
            'surname' => 'User',
            'username' => $user->username,
            'email' => $newEmail,
            'password' => IntegrationTester::PASSWORD . 'N',
            'password2' => IntegrationTester::PASSWORD . 'N',
        ]);
        $I->seeResponseCodeIs(200);

        $saved = $I->reload(UserEntity::class, $user->id);
        $I->assertNotNull($saved);
        $I->assertSame('Edited', $saved->name);
        $I->assertSame($newEmail, $saved->email);
        $I->assertFalse($saved->isActive, 'An unchecked box deactivates the user.');
        $I->assertTrue($I->grabService(Passwords::class)->verify(IntegrationTester::PASSWORD . 'N', $saved->password));
        $I->assertSame(1, $I->em()->getRepository(UserEmailEntity::class)->count(['user' => $user->id, 'email' => $newEmail]));
        $I->assertSame(1, $I->em()->getRepository(UserPasswordEntity::class)->count(['user' => $user->id]));

        $I->seePageIsRefused('/admin.user.edit/default/00000000-0000-4000-8000-000000000000', 404);
    }

    public function theRoleGridOfTheEditAddsAndRemovesTheRole(IntegrationTester $I) : void
    {
        $user = $I->createUser('inroles');
        $role = $I->em()->getRepository(RoleEntity::class)->findOneBy(['name' => 'Normal']);
        $I->assertNotNull($role);
        $I->loginAsAdmin();
        $url = '/admin.user.edit/default/' . $user->uuid;

        $I->amOnPage($url . '?' . http_build_query(['rolesGrid-__id' => $role->id, 'rolesGrid-__key' => 'addRole', 'do' => 'rolesGrid-actionCallback']));
        $I->seeResponseCodeIs(200);
        $I->assertSame(['Normal'], $this->roleNames($I, $user->id));

        $I->seePageIsRefused($url . '?' . http_build_query(['rolesGrid-__id' => $role->id, 'rolesGrid-__key' => 'addRole', 'do' => 'rolesGrid-actionCallback']), 403);

        $I->amOnPage($url . '?' . http_build_query(['rolesGrid-__id' => $role->id, 'rolesGrid-__key' => 'deleteRole', 'do' => 'rolesGrid-actionCallback']));
        $I->seeResponseCodeIs(200);
        $I->assertSame([], $this->roleNames($I, $user->id));

        $I->amOnPage($url . '?' . http_build_query(['rolesGrid-filter' => ['name' => 'norm', 'id' => (string) $role->id], 'rolesGrid-sort' => ['name' => 'DESC']]));
        $I->seeResponseCodeIs(200);
        $I->assertSame([(string) $role->id], (new DatagridPage($I->grabPageSource()))->rowIds('rolesGrid'));
    }

    /**
     * @return list<string>
     */
    private function rowIds(IntegrationTester $I) : array
    {
        return (new DatagridPage($I->grabPageSource()))->rowIds('grid');
    }

    /**
     * @return list<string>
     */
    private function sortedIds(IntegrationTester $I, string $direction) : array
    {
        $I->amOnPage(self::LIST . '?' . http_build_query(['grid-sort' => ['fullName' => $direction], 'grid-perPage' => 100]));
        $I->seeResponseCodeIs(200);

        return $this->rowIds($I);
    }

    private function inlineEdit(IntegrationTester $I, UserEntity $user, string $fullName, string $email, string $isActive) : void
    {
        $I->haveHttpHeader('Sec-Fetch-Site', 'same-origin');
        $I->haveHttpHeader('X-Requested-With', 'XMLHttpRequest');
        $I->sendAjaxPostRequest(self::LIST . '?do=grid-filter-submit', [
            'inline_edit' => [
                '_id' => (string) $user->uuid,
                '_primary_where_column' => 'uuid',
                'fullName' => $fullName,
                'username' => $user->username,
                'email' => $email,
                'isActive' => $isActive,
                'submit' => 'x',
            ],
            'perPage' => '20',
        ]);
        $I->seeResponseCodeIs(200);
    }

    private function deleteUrl(string $uuid) : string
    {
        return self::LIST . '?' . http_build_query(['grid-__id' => $uuid, 'grid-__key' => 'delete', 'do' => 'grid-actionCallback']);
    }

    /**
     * @return list<string>
     */
    private function roleNames(IntegrationTester $I, int $userId) : array
    {
        $user = $I->reload(UserEntity::class, $userId);
        $I->assertNotNull($user);

        return array_values(array_map(static fn (RoleEntity $role) : string => $role->name, $user->roles->toArray()));
    }
}
