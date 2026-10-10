<?php declare(strict_types=1);

namespace Tests\Functional;

use App\Database\EntityManagerDecorator;
use App\Model\Entity\RoleEntity;
use App\Model\Entity\UserEmailEntity;
use App\Model\Entity\UserEntity;
use App\Model\Entity\UserPasswordEntity;
use Nette\Security\Passwords;
use Tests\Support\FunctionalTester;

/**
 * The admin forms of the roles and the users <-> the entities, field by field: what the edit page shows, what an edit / a create
 * writes. (The form <-> entity mapping is `App\Forms\Mapper\RoleFormMapper` / `UserFormMapper`.)
 */
class AdminFormMappingCest
{
    private const PASSWORD = 'Abcdefgh1234!x';

    public function theRoleEditFormShowsTheName(FunctionalTester $I) : void
    {
        $role = $this->role($I);
        $this->login($I);

        $I->amOnPage('/admin.role.edit/default/' . $role->id);
        $I->seeResponseCodeIs(200);
        $I->dontSeeInSource('id="tracy-bs"');

        $I->assertSame($role->name, $I->grabValueFrom('#frm-editForm-name'));
    }

    public function theRoleEditFormWritesTheName(FunctionalTester $I) : void
    {
        $role = $this->role($I);
        $this->login($I);

        $I->amOnPage('/admin.role.edit/default/' . $role->id);
        $I->submitForm('#frm-editForm', ['name' => $role->name . 'Renamed']);
        $I->seeResponseCodeIs(200);
        $I->dontSeeInSource('id="tracy-bs"');

        $em = $this->em($I);
        $I->assertSame($role->name . 'Renamed', $em->getRepository(RoleEntity::class)->find($role->id)?->name);
    }

    /**
     * Found by this test: the create form has no description, the column is NOT NULL, so every create ended in "Operace se nezdařila".
     */
    public function theRoleCreateFormCreatesARole(FunctionalTester $I) : void
    {
        $this->login($I);
        $name = 'MapRole' . random_int(100000, 999999);

        $I->amOnPage('/admin.role.create/');
        $I->submitForm('#frm-createForm', ['name' => $name]);
        $I->seeResponseCodeIs(200);
        $I->dontSeeInSource('id="tracy-bs"');

        $role = $this->em($I)->getRepository(RoleEntity::class)->findOneBy(['name' => $name]);
        $I->assertNotNull($role, 'The role was not created.');
        $I->trackCreatedEntity(RoleEntity::class, $role->id);
    }

    public function theUserEditFormShowsEveryField(FunctionalTester $I) : void
    {
        $user = $this->user($I, 'show', true);
        $this->login($I);

        $I->amOnPage('/admin.user.edit/default/' . $user->uuid);
        $I->seeResponseCodeIs(200);
        $I->dontSeeInSource('id="tracy-bs"');

        $I->assertSame('MapName', $I->grabValueFrom('#frm-editForm-name'));
        $I->assertSame('MapSurname', $I->grabValueFrom('#frm-editForm-surname'));
        $I->assertSame($user->username, $I->grabValueFrom('#frm-editForm-username'));
        $I->assertSame($user->email, $I->grabValueFrom('#frm-editForm-email'));
        $I->seeCheckboxIsChecked('#frm-editForm-isActive');
    }

    public function theUserEditFormWritesTheFieldsAndKeepsThePasswordWhenEmpty(FunctionalTester $I) : void
    {
        $user = $this->user($I, 'edit', true);
        $oldHash = $user->password;
        $tag = (string) random_int(100000, 999999);
        $this->login($I);

        $I->amOnPage('/admin.user.edit/default/' . $user->uuid);
        $I->submitForm('#frm-editForm', [
            'name' => 'NewName',
            'surname' => 'NewSurname',
            'username' => 'mapnew' . $tag,
            'email' => 'mapnew' . $tag . '@example.com',
            'password' => '',
            'password2' => '',
            'isActive' => '1',
        ]);
        $I->seeResponseCodeIs(200);
        $I->dontSeeInSource('id="tracy-bs"');

        $em = $this->em($I);
        $saved = $em->getRepository(UserEntity::class)->find($user->id);

        $I->assertSame('NewName', $saved->name);
        $I->assertSame('NewSurname', $saved->surname);
        $I->assertSame('mapnew' . $tag, $saved->username);
        $I->assertSame('mapnew' . $tag . '@example.com', $saved->email);
        $I->assertTrue($saved->isActive);
        $I->assertSame($oldHash, $saved->password, 'An empty password keeps the password.');
        $I->assertNotNull($saved->updatedAt);

        // a changed e-mail is remembered in the e-mail history
        $emails = array_map(
            static fn(UserEmailEntity $e): string => $e->email,
            $em->getRepository(UserEmailEntity::class)->findBy(['user' => $saved->id]),
        );
        $I->assertContains('mapnew' . $tag . '@example.com', $emails);
    }

    public function theUserEditFormDeactivatesAndChangesThePassword(FunctionalTester $I) : void
    {
        $user = $this->user($I, 'pass', true);
        $oldHash = $user->password;
        $this->login($I);

        // posted as a browser does it: an unchecked box is simply not sent (the test client would keep the checked state of the page)
        $I->amOnPage('/admin.user.edit/default/' . $user->uuid);
        $I->haveHttpHeader('Sec-Fetch-Site', 'same-origin');
        $I->haveHttpHeader('X-Requested-With', 'XMLHttpRequest');
        $I->sendAjaxPostRequest('/admin.user.edit/default/' . $user->uuid . '?do=editForm-submit', [
            'name' => 'MapName',
            'surname' => 'MapSurname',
            'username' => $user->username,
            'email' => $user->email,
            'password' => self::PASSWORD . 'N',
            'password2' => self::PASSWORD . 'N',
            'send' => 'x',
        ]);
        $I->seeResponseCodeIs(200);
        $I->dontSeeInSource('id="tracy-bs"');

        $em = $this->em($I);
        $saved = $em->getRepository(UserEntity::class)->find($user->id);

        $I->assertFalse($saved->isActive, 'An unchecked box deactivates the user.');
        $I->assertNotSame($oldHash, $saved->password);
        $I->assertTrue($I->grabService(Passwords::class)->verify(self::PASSWORD . 'N', $saved->password));

        // the password history keeps both
        $hashes = array_map(
            static fn(UserPasswordEntity $p): string => $p->password,
            $em->getRepository(UserPasswordEntity::class)->findBy(['user' => $saved->id]),
        );
        $I->assertContains($saved->password, $hashes);
    }

    public function theUserCreateFormCreatesAUser(FunctionalTester $I) : void
    {
        $this->login($I);
        $tag = (string) random_int(100000, 999999);

        $I->amOnPage('/admin.user.create/');
        $I->submitForm('#frm-createForm', [
            'name' => 'Created',
            'surname' => 'Person',
            'username' => 'mapcreate' . $tag,
            'email' => 'mapcreate' . $tag . '@example.com',
            'password' => self::PASSWORD,
            'password2' => self::PASSWORD,
            'isActive' => '1',
        ]);
        $I->seeResponseCodeIs(200);
        $I->dontSeeInSource('id="tracy-bs"');

        $em = $this->em($I);
        $created = $em->getRepository(UserEntity::class)->findOneBy(['username' => 'mapcreate' . $tag]);

        $I->assertNotNull($created, 'The user was not created.');
        $I->trackCreatedEntity(UserEntity::class, $created->id);

        $I->assertSame('Created', $created->name);
        $I->assertSame('Person', $created->surname);
        $I->assertSame('mapcreate' . $tag . '@example.com', $created->email);
        $I->assertTrue($created->isActive);
        $I->assertTrue($I->grabService(Passwords::class)->verify(self::PASSWORD, $created->password));
        $I->assertCount(1, $em->getRepository(UserPasswordEntity::class)->findBy(['user' => $created->id]));
        $I->assertCount(1, $em->getRepository(UserEmailEntity::class)->findBy(['user' => $created->id]));
    }

    private function login(FunctionalTester $I) : void
    {
        $I->amOnPage('/web.user.login/');
        $I->submitForm('#frm-loginForm', ['username' => 'TestUsername', 'password' => 'secret']);
        $I->seeResponseCodeIs(200);
    }

    private function em(FunctionalTester $I) : EntityManagerDecorator
    {
        /** @var EntityManagerDecorator $em */
        $em = $I->grabService(EntityManagerDecorator::class);
        $em->clear();

        return $em;
    }

    private function role(FunctionalTester $I) : RoleEntity
    {
        $em = $this->em($I);

        $role = new RoleEntity();
        $role->name = 'MapRole' . random_int(100000, 999999);
        $role->description = 'test';
        $em->persist($role);
        $em->flush();
        $I->trackCreatedEntity(RoleEntity::class, $role->id);

        return $role;
    }

    private function user(FunctionalTester $I, string $label, bool $active) : UserEntity
    {
        $em = $this->em($I);
        $tag = $label . random_int(100000, 999999);

        $user = new UserEntity();
        $user->name = 'MapName';
        $user->surname = 'MapSurname';
        $user->username = 'map' . $tag;
        $user->email = 'map' . $tag . '@example.com';
        $user->password = $I->grabService(Passwords::class)->hash(self::PASSWORD);
        $user->isActive = $active;
        $em->persist($user);
        $em->flush();
        $I->trackCreatedEntity(UserEntity::class, $user->id);

        return $user;
    }
}
