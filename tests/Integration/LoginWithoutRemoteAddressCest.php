<?php declare(strict_types=1);

namespace Tests\Integration;

use App\Model\Entity\UserAutoLoginEntity;
use Nette\Http\IRequest;
use Tests\Support\Http\Request;
use Tests\Support\IntegrationTester;

/**
 * A request without a remote address (the console, some proxies) still saves the "stay logged in" token: the column of the
 * IP address is PostgreSQL `inet`, a word like 'Unknown' made the INSERT fail.
 */
class LoginWithoutRemoteAddressCest
{
    public function stayLoggedInIsSaved(IntegrationTester $I) : void
    {
        $request = $I->grabService(IRequest::class);
        $I->assertInstanceOf(Request::class, $request);
        $request->hideRemoteAddress();
        $user = $I->createUser('noaddress');

        $I->amOnPage('/web.user.login/');
        $I->checkOption('#frm-loginForm-stayLoggedIn');
        $I->submitForm('#frm-loginForm', ['username' => $user->username, 'password' => IntegrationTester::PASSWORD]);
        $I->seeResponseCodeIs(200);

        $em = $I->em();
        $em->clear();
        $autoLogin = $em->getRepository(UserAutoLoginEntity::class)->findOneBy(['user' => $user->id]);
        $I->assertNotNull($autoLogin, 'The "stay logged in" token was not saved.');
        $I->assertSame('0.0.0.0', $autoLogin->ipAddress);
        $I->assertNull($autoLogin->countryCode);
    }
}
