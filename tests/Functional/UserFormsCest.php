<?php declare(strict_types=1);

namespace Tests\Functional;

use App\Database\EntityManagerDecorator;
use App\Model\Entity\RoleEntity;
use App\Model\Entity\UserEntity;
use Tests\Support\FunctionalTester;

/**
 * Forms and datagrids of the users, submitted the way the browser does it. They read their values through typed value
 * classes (`$form->getValues(XValues::class)`), so a wrong property type or a missing property shows up here as a 500.
 */
class UserFormsCest
{
    private const PASSWORD = 'Abcdefgh1234!x';

    public function publicPagesRender(FunctionalTester $I) : void
    {
        foreach (['/', '/web.user.login/', '/web.user.registration/', '/web.user.forget-password.request/'] as $url) {
            $I->amOnPage($url);
            $I->seeResponseCodeIs(200);
            $I->dontSeeInSource('id="tracy-bs"');
        }
    }

    public function registrationCreatesAnInactiveUser(FunctionalTester $I) : void
    {
        $tag = (string) random_int(100000, 999999);

        $I->amOnPage('/web.user.registration/');
        $I->submitForm('#frm-registrationForm', [
            'name' => 'Reg',
            'surname' => 'Test',
            'username' => 'regtest' . $tag,
            'email' => 'regtest' . $tag . '@example.com',
            'password' => self::PASSWORD,
            'password2' => self::PASSWORD,
        ]);
        $I->seeResponseCodeIs(200);
        $I->dontSeeInSource('id="tracy-bs"');

        $user = $this->findUser($I, 'regtest' . $tag);
        $I->assertNotNull($user, 'The registration did not create the user.');
        $I->trackCreatedEntity(UserEntity::class, $user->id);
        $I->assertFalse($user->isActive);
        $I->assertSame('Reg', $user->name);
    }

    public function loginWithWrongPasswordShowsAnError(FunctionalTester $I) : void
    {
        $I->amOnPage('/web.user.login/');
        $I->submitForm('#frm-loginForm', ['username' => 'TestUsername', 'password' => 'definitely-wrong']);
        $I->seeResponseCodeIs(200);
        $I->dontSeeInSource('id="tracy-bs"');
        $I->see('Nesprávné heslo.');
    }

    public function adminUserAndRoleGridsInlineEdit(FunctionalTester $I) : void
    {
        $this->login($I);

        /** @var EntityManagerDecorator $em */
        $em = $I->grabService(EntityManagerDecorator::class);

        $tag = (string) random_int(100000, 999999);
        $user = $this->registerUser($I, $tag);

        $I->haveHttpHeader('Sec-Fetch-Site', 'same-origin');
        $I->haveHttpHeader('X-Requested-With', 'XMLHttpRequest');

        $I->sendAjaxPostRequest('/admin.user.list/?do=grid-filter-submit', [
            'inline_edit' => [
                '_id' => (string) $user->uuid,
                '_primary_where_column' => 'uuid',
                'fullName' => 'Grid Renamed',
                'username' => 'regtest' . $tag,
                'email' => 'regtest' . $tag . '@example.com',
                'isActive' => '1',
                'submit' => 'x',
            ],
            'perPage' => '20',
        ]);
        $I->seeResponseCodeIs(200);

        $em->clear();
        $edited = $this->findUser($I, 'regtest' . $tag);
        $I->assertSame('Grid', $edited?->name);
        $I->assertSame('Renamed', $edited?->surname);
        $I->assertTrue($edited?->isActive);
    }

    public function adminRoleGridInlineEdit(FunctionalTester $I) : void
    {
        $this->login($I);

        /** @var EntityManagerDecorator $em */
        $em = $I->grabService(EntityManagerDecorator::class);

        $role = new RoleEntity();
        $role->name = 'GridRole' . random_int(100000, 999999);
        $role->description = 'test';
        $em->persist($role);
        $em->flush();

        $I->haveHttpHeader('Sec-Fetch-Site', 'same-origin');
        $I->haveHttpHeader('X-Requested-With', 'XMLHttpRequest');

        $I->sendAjaxPostRequest('/admin.role.list/?do=grid-filter-submit', [
            'inline_edit' => ['_id' => (string) $role->id, '_primary_where_column' => 'id', 'name' => $role->name . 'X', 'submit' => 'x'],
            'perPage' => '20',
        ]);
        $I->seeResponseCodeIs(200);

        $em->clear();
        $I->assertSame($role->name . 'X', $em->getRepository(RoleEntity::class)->find($role->id)?->name);
    }

    private function login(FunctionalTester $I) : void
    {
        $I->amOnPage('/web.user.login/');
        $I->submitForm('#frm-loginForm', ['username' => 'TestUsername', 'password' => 'secret']);
        $I->seeResponseCodeIs(200);
    }

    private function registerUser(FunctionalTester $I, string $tag) : UserEntity
    {
        $I->amOnPage('/web.user.registration/');
        $I->submitForm('#frm-registrationForm', [
            'name' => 'Reg', 'surname' => 'Test', 'username' => 'regtest' . $tag, 'email' => 'regtest' . $tag . '@example.com',
            'password' => self::PASSWORD, 'password2' => self::PASSWORD,
        ]);

        $user = $this->findUser($I, 'regtest' . $tag);
        $I->assertNotNull($user, 'The registration did not create the user.');
        $I->trackCreatedEntity(UserEntity::class, $user->id);

        return $user;
    }

    private function findUser(FunctionalTester $I, string $username) : ?UserEntity
    {
        /** @var EntityManagerDecorator $em */
        $em = $I->grabService(EntityManagerDecorator::class);
        $em->clear();

        return $em->getRepository(UserEntity::class)->findOneBy(['username' => $username]);
    }
}
