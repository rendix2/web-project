<?php declare(strict_types=1);

namespace Tests\Functional;

use App\Core\AutoLoginAuthenticator;
use App\Database\EntityManagerDecorator;
use App\Model\Entity\UserAutoLoginEntity;
use App\Model\Entity\UserEntity;
use App\Model\Facade\UserFacade;
use App\UI\Admin\User\Edit\EditUserValues;
use DateTimeImmutable;
use Nette\Security\Passwords;
use Tests\Support\FunctionalTester;

/**
 * The "stay logged in" cookie (autoLogin) and the session of a user that gets deactivated:
 * - the cookie holds a random token, the database only its SHA-256 hash (a leaked table logs nobody in) in a UNIQUE column,
 * - a token expires (`validUntil`) and is replaced by a new one every time it is used (a stolen one works once at most),
 * - an inactive user is not logged in by it and his tokens are deleted,
 * - deactivating a user ends his session at the very next request, and removes his tokens.
 */
class AutoLoginSecurityCest
{
    private const PASSWORD = 'Abcdefgh1234!x';

    public function theDatabaseHoldsOnlyTheHashOfTheToken(FunctionalTester $I) : void
    {
        $user = $this->createUser($I, 'hash');
        $token = $this->loginAndStay($I, $user);

        $row = $this->onlyRow($I, $user);

        $I->assertNotSame($token, $row->tokenHash);
        $I->assertSame(hash('sha256', $token), $row->tokenHash);
        $I->assertGreaterThan(new DateTimeImmutable('+29 days'), $row->validUntil);
        $I->assertLessThanOrEqual(new DateTimeImmutable('+31 days'), $row->validUntil);
    }

    public function theTokenIsReplacedWhenItIsUsedAndTheOldOneStopsWorking(FunctionalTester $I) : void
    {
        $user = $this->createUser($I, 'rotate');
        $oldToken = $this->loginAndStay($I, $user);

        // the browser was closed: only the autoLogin cookie is left
        $I->resetCookie('PHPSESSID');
        $I->amOnPage('/web.user.login/');
        $I->see('Již jste přihlášeni.');

        $newToken = $I->grabCookie(AutoLoginAuthenticator::COOKIE_NAME);
        $I->assertNotSame($oldToken, $newToken, 'The token was not rotated.');
        $I->assertSame(hash('sha256', $newToken), $this->onlyRow($I, $user)->tokenHash);

        // somebody who copied the OLD cookie
        $I->resetCookie('PHPSESSID');
        $I->setCookie(AutoLoginAuthenticator::COOKIE_NAME, $oldToken);
        $I->amOnPage('/web.user.login/');
        $I->dontSee('Již jste přihlášeni.');
        $I->dontSeeInSource('id="tracy-bs"');
    }

    public function anExpiredTokenDoesNotLogAnybodyIn(FunctionalTester $I) : void
    {
        $user = $this->createUser($I, 'expired');
        $this->loginAndStay($I, $user);

        $row = $this->onlyRow($I, $user);
        $row->validUntil = new DateTimeImmutable('-1 minute');
        $this->em($I)->flush();

        $I->resetCookie('PHPSESSID');
        $I->amOnPage('/web.user.login/');

        $I->dontSee('Již jste přihlášeni.');
        $I->dontSeeInSource('id="tracy-bs"');
        $I->assertSame(0, $this->rowCount($I, $user), 'An expired token stayed in the database.');
    }

    public function anInactiveUserIsNotLoggedInByTheTokenAndTheTokensAreDeleted(FunctionalTester $I) : void
    {
        $user = $this->createUser($I, 'inactive');
        $this->loginAndStay($I, $user);

        $this->deactivateInDatabase($I, $user);

        $I->resetCookie('PHPSESSID');
        $I->amOnPage('/web.user.login/');

        $I->dontSee('Již jste přihlášeni.');
        $I->dontSeeInSource('id="tracy-bs"');
        $I->assertSame(0, $this->rowCount($I, $user), 'The tokens of an inactive user stayed in the database.');
    }

    public function anInactiveUserCannotEnterTheAdminByTheToken(FunctionalTester $I) : void
    {
        $user = $this->createUser($I, 'admininactive');
        $this->loginAndStay($I, $user);
        $this->deactivateInDatabase($I, $user);

        $I->resetCookie('PHPSESSID');
        $I->amOnPage('/admin.user.list/');

        $I->dontSeeElement('#admin-navigation');
    }

    public function deactivatingAUserEndsHisSessionAtTheNextRequest(FunctionalTester $I) : void
    {
        $user = $this->createUser($I, 'session');

        $I->amOnPage('/web.user.login/');
        $I->submitForm('#frm-loginForm', ['username' => $user->username, 'password' => self::PASSWORD]);
        $I->amOnPage('/web.user.login/');
        $I->see('Již jste přihlášeni.');

        $this->deactivateInDatabase($I, $user);

        // the same session, the same cookie
        $I->amOnPage('/web.user.login/');
        $I->dontSee('Již jste přihlášeni.');
        $I->dontSeeInSource('id="tracy-bs"');

        $I->amOnPage('/web.user.edit/');
        $I->dontSeeElement('#frm-editForm');
    }

    public function deactivatingAUserInTheAdminDeletesHisTokens(FunctionalTester $I) : void
    {
        $user = $this->createUser($I, 'facade');
        $this->loginAndStay($I, $user);
        $I->assertSame(1, $this->rowCount($I, $user));

        $values = new EditUserValues();
        $values->name = $user->name;
        $values->surname = $user->surname;
        $values->username = $user->username;
        $values->email = $user->email;
        $values->password = '';
        $values->isActive = false;

        /** @var UserFacade $facade */
        $facade = $I->grabService(UserFacade::class);
        $facade->update((string) $user->uuid, $values);

        $I->assertSame(0, $this->rowCount($I, $user), 'Deactivating the user did not delete his tokens.');
    }

    private function loginAndStay(FunctionalTester $I, UserEntity $user) : string
    {
        $I->amOnPage('/web.user.login/');
        $I->checkOption('#frm-loginForm-stayLoggedIn');
        $I->submitForm('#frm-loginForm', ['username' => $user->username, 'password' => self::PASSWORD]);
        $I->seeResponseCodeIs(200);
        $I->dontSeeInSource('id="tracy-bs"');

        return $I->grabCookie(AutoLoginAuthenticator::COOKIE_NAME);
    }

    private function onlyRow(FunctionalTester $I, UserEntity $user) : UserAutoLoginEntity
    {
        $em = $this->em($I);
        $em->clear();
        $rows = $em->getRepository(UserAutoLoginEntity::class)->findBy(['user' => $user->id]);
        $I->assertCount(1, $rows);

        return $rows[0];
    }

    private function rowCount(FunctionalTester $I, UserEntity $user) : int
    {
        $em = $this->em($I);
        $em->clear();

        return $em->getRepository(UserAutoLoginEntity::class)->count(['user' => $user->id]);
    }

    private function deactivateInDatabase(FunctionalTester $I, UserEntity $user) : void
    {
        $em = $this->em($I);
        $em->createQuery('UPDATE ' . UserEntity::class . ' u SET u.isActive = false WHERE u.id = :id')->setParameter('id', $user->id)->execute();
        $em->clear();
    }

    private function createUser(FunctionalTester $I, string $label) : UserEntity
    {
        $tag = $label . random_int(100000, 999999);
        /** @var Passwords $passwords */
        $passwords = $I->grabService(Passwords::class);
        $em = $this->em($I);

        $user = new UserEntity();
        $user->name = 'Auto';
        $user->surname = 'Login';
        $user->username = $tag;
        $user->email = $tag . '@example.com';
        $user->password = $passwords->hash(self::PASSWORD);
        $user->isActive = true;

        $em->persist($user);
        $em->flush();
        $I->trackCreatedEntity(UserEntity::class, $user->id);

        return $user;
    }

    private function em(FunctionalTester $I) : EntityManagerDecorator
    {
        /** @var EntityManagerDecorator $em */
        $em = $I->grabService(EntityManagerDecorator::class);

        return $em;
    }

}
