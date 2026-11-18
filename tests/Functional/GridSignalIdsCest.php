<?php declare(strict_types=1);

namespace Tests\Functional;

use App\Database\EntityManagerDecorator;
use App\Model\Entity\RoleEntity;
use App\Model\Entity\UserEntity;
use Codeception\Attribute\Group;
use Nette\Security\Passwords;
use Ramsey\Uuid\Uuid;
use Tests\Support\FunctionalTester;

/**
 * The ids of the datagrid signals come straight from the request. A malformed one (`abc`, `1.5`, beyond bigint, no uuid) reached
 * Doctrine / PostgreSQL (`invalid input syntax`, ValueNotConvertible: a 500 with an exception in the log), an id of no row reached
 * the handler. `SecuredDatagrid` answers both with a 404 before any handler runs, and ignores a forged
 * inline_edit[_primary_where_column] (it ended up in DQL). The inline edit submits are sent the way the browser sends them.
 */
#[Group('grid')]
class GridSignalIdsCest
{
    public function _before(FunctionalTester $I) : void
    {
        $I->loginAs(FunctionalTester::ROLE_ADMIN);
    }

    public function aMalformedOrMissingIdOfAnActionIsNotFound(FunctionalTester $I) : void
    {
        $role = $this->createRole($I);

        foreach (['abc', '1.5', '', '99999999999999999999999', '1 OR 1=1', '2147483648', '999999999'] as $id) {
            $I->amOnPage('/admin.role.list/?' . http_build_query(['grid-__id' => $id, 'grid-__key' => 'delete', 'do' => 'grid-actionCallback']));
            $I->seeRefused(404, 'role delete ' . $id);
        }

        foreach (['abc', '1', Uuid::uuid4()->toString()] as $id) {
            $I->amOnPage('/admin.user.list/?' . http_build_query(['grid-__id' => $id, 'grid-__key' => 'delete', 'do' => 'grid-actionCallback']));
            $I->seeRefused(404, 'user delete ' . $id);
        }

        $I->assertTrue($this->roleExists($I, $role->id));
    }

    public function aMalformedOrMissingIdOfTheInlineEditIsNotFound(FunctionalTester $I) : void
    {
        foreach (['abc', '1.5', '99999999999999999999999'] as $id) {
            $this->ajax($I);
            $I->sendAjaxGetRequest('/admin.role.list/?' . http_build_query(['grid-id' => $id, 'do' => 'grid-inlineEdit']));
            $I->seeRefused(404, 'role inline edit ' . $id);
        }

        $role = $this->createRole($I);

        foreach (['abc', '999999999'] as $id) {
            $this->ajax($I);
            $I->sendAjaxPostRequest('/admin.role.list/?do=grid-filter-submit', [
                'inline_edit' => ['_id' => $id, '_primary_where_column' => 'id', 'name' => 'Hacked' . $role->id, 'submit' => 'Uložit'],
            ]);
            $I->seeRefused(404, 'role inline submit ' . $id);
        }

        foreach (['abc', Uuid::uuid4()->toString()] as $id) {
            $this->ajax($I);
            $I->sendAjaxPostRequest('/admin.user.list/?do=grid-filter-submit', [
                'inline_edit' => ['_id' => $id, '_primary_where_column' => 'uuid', 'fullName' => 'Hacked User', 'username' => 'hacked' . $role->id, 'email' => 'hacked' . $role->id . '@example.com', 'isActive' => '1', 'submit' => 'Uložit'],
            ]);
            $I->seeRefused(404, 'user inline submit ' . $id);
        }

        $I->assertSame($role->name, $this->roleName($I, $role->id), 'A refused inline edit changed the role.');
    }

    public function aForgedPrimaryWhereColumnIsIgnored(FunctionalTester $I) : void
    {
        $role = $this->createRole($I);
        $user = $this->createUser($I);

        $this->ajax($I);
        $I->sendAjaxPostRequest('/admin.role.list/?do=grid-filter-submit', [
            'inline_edit' => ['_id' => (string) $role->id, '_primary_where_column' => 'id) OR (1=1', 'name' => $role->name . 'x', 'submit' => 'Uložit'],
        ]);
        $I->dontSeeInSource('id="tracy-bs"');
        $I->assertSame($role->name . 'x', $this->roleName($I, $role->id), 'The inline edit with a forged column did not save (the test request is wrong).');

        $this->ajax($I);
        $I->sendAjaxPostRequest('/admin.user.list/?do=grid-filter-submit', [
            'inline_edit' => ['_id' => (string) $user->uuid, '_primary_where_column' => 'nope', 'fullName' => 'Grid Renamed', 'username' => $user->username, 'email' => $user->email, 'isActive' => '1', 'submit' => 'Uložit'],
        ]);
        $I->dontSeeInSource('id="tracy-bs"');
    }

    private function ajax(FunctionalTester $I) : void
    {
        $I->haveHttpHeader('Sec-Fetch-Site', 'same-origin');
        $I->haveHttpHeader('X-Requested-With', 'XMLHttpRequest');
    }

    private function roleExists(FunctionalTester $I, int $id) : bool
    {
        return $this->roleName($I, $id) !== null;
    }

    private function roleName(FunctionalTester $I, int $id) : ?string
    {
        $em = $this->em($I);
        $em->clear();

        return $em->getRepository(RoleEntity::class)->find($id)?->name;
    }

    private function createRole(FunctionalTester $I) : RoleEntity
    {
        $em = $this->em($I);

        $role = new RoleEntity();
        $role->name = 'GridSignal' . random_int(100000, 999999);
        $role->description = 'grid signal test';

        $em->persist($role);
        $em->flush();
        $I->trackCreatedEntity(RoleEntity::class, $role->id);

        return $role;
    }

    private function createUser(FunctionalTester $I) : UserEntity
    {
        $tag = 'gridsignal' . random_int(100000, 999999);
        $em = $this->em($I);

        $user = new UserEntity();
        $user->name = 'Grid';
        $user->surname = 'Signal';
        $user->username = $tag;
        $user->email = $tag . '@example.com';
        $user->password = $I->grabService(Passwords::class)->hash('Abcdefgh1234!x');
        $user->isActive = true;

        $em->persist($user);
        $em->flush();
        $I->trackCreatedEntity(UserEntity::class, $user->id);

        return $user;
    }

    private function em(FunctionalTester $I) : EntityManagerDecorator
    {
        return $I->grabService(EntityManagerDecorator::class);
    }
}
