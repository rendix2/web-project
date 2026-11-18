<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Database\EntityManagerDecorator;
use App\Model\Entity\RoleEntity;
use App\Model\Entity\UserEntity;
use Nette\Security\Passwords;

/**
 * Inherited Methods
 * @method void wantTo($text)
 * @method void wantToTest($text)
 * @method void execute($callable)
 * @method void expectTo($prediction)
 * @method void expect($prediction)
 * @method void amGoingTo($argumentation)
 * @method void am($role)
 * @method void lookForwardTo($achieveValue)
 * @method void comment($description)
 * @method void pause($vars = [])
 *
 * @SuppressWarnings(PHPMD)
*/
class FunctionalTester extends \Codeception\Actor
{
    use _generated\FunctionalTesterActions;

    /** the roles of the smoke tests: nobody logged in, a plain user (role Normal), an admin (role Admin) */
    public const ROLE_ANONYMOUS = 'anonymous';
    public const ROLE_USER = 'User';
    public const ROLE_ADMIN = 'Admin';

    private const PASSWORD = 'Abcdefgh1234!x';

    /**
     * Logs in as the role: the admin is the fixture user TestUsername; the plain user is created for the test (role Normal, removed
     * after the test by CleanupTestData), the fixtures have no such account.
     */
    public function loginAs(string $role) : void
    {
        if ($role === self::ROLE_ANONYMOUS) {
            return;
        }

        if ($role === self::ROLE_ADMIN) {
            $this->login('TestUsername', 'secret');

            return;
        }

        $this->login($this->createPlainUser()->username, self::PASSWORD);
    }

    public function login(string $username, string $password) : void
    {
        $this->amOnPage('/web.user.login/');
        $this->submitForm('#frm-loginForm', ['username' => $username, 'password' => $password]);
        $this->seeResponseCodeIs(200);
        $this->dontSeeElement('#frm-loginForm');
    }

    /**
     * The request was refused with the code (400 - 499), by a BadRequestException - not by any other exception. Debug mode is always
     * on in this project (AGENTS.md "Known caveats"): Tracy shows the BadRequestException as an HTTP 500 bluescreen (an AJAX request
     * gets it as plain text) with the code in it. Without Tracy (production) it is the code itself.
     */
    public function seeRefused(int $code, string $what = '') : void
    {
        $source = $this->grabPageSource();

        // AJAX: the exception as plain text, the code is in the call of error()
        if (str_starts_with($source, 'Nette\\Application\\')) {
            $this->seeResponseCodeIs(500);
            $this->assertStringStartsWith('Nette\\Application\\BadRequestException:', $source, $what);
            $this->assertStringContainsString('->error(', $source, $what);
            $this->assertStringContainsString(', ' . $code . ')', $source, $what);

            return;
        }

        if (!str_contains($source, 'id="tracy-bs"')) {
            $this->seeResponseCodeIs($code);

            return;
        }

        // the title of the bluescreen names the exception (a PostgreSQL / Doctrine / datagrid one would be there instead)
        $this->seeResponseCodeIs(500);
        $title = preg_match('~<title>(.*?)</title>~s', $source, $m) === 1 ? html_entity_decode($m[1]) : '';
        $this->assertStringStartsWith('Nette\\Application\\BadRequestException', $title, $what);
        $this->assertStringEndsWith('#' . $code, $title, $what);
    }

    /**
     * A page that answers 200 and renders no exception.
     */
    public function seePageIsOk(string $what = '') : void
    {
        $this->seeResponseCodeIs(200);
        $source = $this->grabPageSource();
        $this->assertStringNotContainsString('id="tracy-bs"', $source, $what);
        $this->assertStringNotContainsString('Nette\\Application\\BadRequestException', $source, $what);
    }

    private function createPlainUser() : UserEntity
    {
        $em = $this->grabService(EntityManagerDecorator::class);
        $role = $em->getRepository(RoleEntity::class)->findOneBy(['name' => 'Normal']);
        $this->assertNotNull($role, 'The fixture role Normal is missing.');

        $tag = 'plainuser' . random_int(100000, 999999);

        $user = new UserEntity();
        $user->name = 'Plain';
        $user->surname = 'User';
        $user->username = $tag;
        $user->email = $tag . '@example.com';
        $user->password = $this->grabService(Passwords::class)->hash(self::PASSWORD);
        $user->isActive = true;
        $user->addRoleEntity($role);

        $em->persist($user);
        $em->flush();
        $this->trackCreatedEntity(UserEntity::class, $user->id);

        return $user;
    }
}
