<?php declare(strict_types=1);

namespace Tests\Acceptance;

use Codeception\Util\Locator;
use Tests\Support\AcceptanceTester;

/**
 * The eye button of a password field shows and hides the password. Its script lives in assets/app.js: an inline script of
 * the layout is blocked by the Content-Security-Policy.
 */
class PasswordToggleCest
{
    public function theEyeButtonShowsAndHidesThePassword(AcceptanceTester $I) : void
    {
        $button = Locator::firstElement('button[data-password-toggle]');
        $hidden = Locator::find('input', ['name' => 'password', 'type' => 'password']);
        $shown = Locator::find('input', ['name' => 'password', 'type' => 'text']);

        $I->amOnPage('/web.user.registration/');
        $I->seeElement($hidden);

        $I->click($button);
        $I->seeElement($shown);

        $I->click($button);
        $I->seeElement($hidden);
    }
}
