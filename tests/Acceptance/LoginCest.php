<?php declare(strict_types=1);

namespace Tests\Acceptance;

use Tests\Support\AcceptanceTester;

/**
 * Real browser (Selenium / Chrome): the login form of the fixture admin user.
 */
class LoginCest
{
    public function loginPageRenders(AcceptanceTester $I) : void
    {
        $I->amOnPage('/web.user.login/');
        $I->seeElement('#frm-loginForm');
        $I->dontSeeElement('#tracy-bs');
    }

    public function adminCanLogIn(AcceptanceTester $I) : void
    {
        $I->amOnPage('/web.user.login/');
        $I->fillField('username', 'TestUsername');
        $I->fillField('password', 'secret');
        $I->click('#frm-loginForm [type=submit]');
        $I->wait(1);
        $I->dontSeeElement('#tracy-bs');
    }
}
