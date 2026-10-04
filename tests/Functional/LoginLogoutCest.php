<?php declare(strict_types=1);

namespace Tests\Functional;

use App\Core\AutoLoginAuthenticator;
use App\Database\EntityManagerDecorator;
use App\Model\Entity\UserAutoLoginEntity;
use App\Model\Entity\UserEntity;
use Nette\Security\Passwords;
use Tests\Support\FunctionalTester;

/**
 * Login/logout, submitted the way the browser does it. Covers the bugs found/fixed 2026-09-28:
 * "stay logged in" not actually persisting (session-only cookie, unconditional 2h session
 * expiration, and the autologin cookie never being consulted outside the Admin module), logout
 * leaving the autologin cookie behind after removing its DB row, and the hardcoded/untranslated
 * "already logged in" message.
 */
class LoginLogoutCest
{
    private const PASSWORD = 'Abcdefgh1234!x';

    public function stayingLoggedInPersistsAcrossANewBrowserSession(FunctionalTester $I) : void
    {
        $userEntity = $this->createActiveUser($I, 'stay');

        $I->amOnPage('/web.user.login/');
        $I->checkOption('#frm-loginForm-stayLoggedIn');
        $I->submitForm('#frm-loginForm', ['username' => $userEntity->username, 'password' => self::PASSWORD]);
        $I->seeResponseCodeIs(200);
        $I->dontSeeInSource('id="tracy-bs"');
        $I->seeCookie(AutoLoginAuthenticator::COOKIE_NAME);

        /** @var EntityManagerDecorator $em */
        $em = $I->grabService(EntityManagerDecorator::class);
        $em->clear();
        $autoLoginCount = $em->getRepository(UserAutoLoginEntity::class)->count(['user' => $userEntity->id]);
        $I->assertSame(1, $autoLoginCount, 'Checking "stay logged in" did not create a UserAutoLoginEntity.');

        // Simulate closing and reopening the browser: the session cookie is gone, only the
        // persistent autoLogin cookie survives - exactly what AutoLoginService::tryLogin (wired
        // onto Application::onPresenter in Bootstrap::runWebApplication()) is meant to handle.
        $I->resetCookie('PHPSESSID');

        $I->amOnPage('/web.user.login/');
        $I->seeResponseCodeIs(200);
        $I->dontSeeInSource('id="tracy-bs"');
        $I->see('Již jste přihlášeni.');
    }

    public function loggingInWithoutStayLoggedInDoesNotCreateAnAutoLoginCookie(FunctionalTester $I) : void
    {
        $userEntity = $this->createActiveUser($I, 'nostay');

        $I->amOnPage('/web.user.login/');
        $I->submitForm('#frm-loginForm', ['username' => $userEntity->username, 'password' => self::PASSWORD]);
        $I->seeResponseCodeIs(200);
        $I->dontSeeInSource('id="tracy-bs"');
        $I->dontSeeCookie(AutoLoginAuthenticator::COOKIE_NAME);

        $I->resetCookie('PHPSESSID');

        $I->amOnPage('/web.user.login/');
        $I->dontSee('Již jste přihlášeni.');
    }

    public function loggingOutRemovesTheAutoLoginCookieAndEntity(FunctionalTester $I) : void
    {
        $userEntity = $this->createActiveUser($I, 'logout');

        $I->amOnPage('/web.user.login/');
        $I->checkOption('#frm-loginForm-stayLoggedIn');
        $I->submitForm('#frm-loginForm', ['username' => $userEntity->username, 'password' => self::PASSWORD]);
        $I->seeResponseCodeIs(200);
        $I->seeCookie(AutoLoginAuthenticator::COOKIE_NAME);

        $I->amOnPage('/web.user.logout/');
        $I->seeResponseCodeIs(200);
        $I->dontSeeInSource('id="tracy-bs"');
        $I->dontSeeCookie(AutoLoginAuthenticator::COOKIE_NAME);

        /** @var EntityManagerDecorator $em */
        $em = $I->grabService(EntityManagerDecorator::class);
        $em->clear();
        $autoLoginCount = $em->getRepository(UserAutoLoginEntity::class)->count(['user' => $userEntity->id]);
        $I->assertSame(0, $autoLoginCount, 'Logout did not remove the UserAutoLoginEntity.');

        // A stale/removed autologin cookie must never break a public page - AutoLoginService
        // swallows the resulting AuthenticationException and continues as not logged in.
        $I->resetCookie('PHPSESSID');
        $I->amOnPage('/web.user.login/');
        $I->seeResponseCodeIs(200);
        $I->dontSeeInSource('id="tracy-bs"');
        $I->dontSee('Již jste přihlášeni.');
    }

    public function loggingOutWhileAlreadyLoggedOutRedirectsInsteadOfErroring(FunctionalTester $I) : void
    {
        $I->amOnPage('/web.user.logout/');
        $I->seeResponseCodeIs(200);
        $I->dontSeeInSource('id="tracy-bs"');
        $I->seeCurrentUrlEquals('/web.user.login/');
    }

    private function createActiveUser(FunctionalTester $I, string $label) : UserEntity
    {
        $tag = $label . random_int(100000, 999999);
        $passwords = $I->grabService(Passwords::class);

        /** @var EntityManagerDecorator $em */
        $em = $I->grabService(EntityManagerDecorator::class);

        $userEntity = new UserEntity();
        $userEntity->name = 'Login';
        $userEntity->surname = 'Test';
        $userEntity->username = $tag;
        $userEntity->email = $tag . '@example.com';
        $userEntity->password = $passwords->hash(self::PASSWORD);
        $userEntity->isActive = true;

        $em->persist($userEntity);
        $em->flush();
        $I->trackCreatedEntity(UserEntity::class, $userEntity->id);

        return $userEntity;
    }
}
