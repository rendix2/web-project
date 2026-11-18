<?php declare(strict_types=1);

namespace Tests\Integration;

use App\Core\AutoLoginAuthenticator;
use App\Core\AutoLoginService;
use App\Core\AutoLoginTokenService;
use App\Model\Entity\UserAutoLoginEntity;
use DateTimeImmutable;
use Nette\Application\Application;
use Nette\Security\AuthenticationException;
use Tests\Support\IntegrationTester;

/**
 * "Stay logged in": the tokens (only their hash is stored, an expired one is gone, a used one is replaced), the authenticator of
 * the cookie and AutoLoginService::tryLogin() - the hook of every request (Bootstrap->runWebApplication()).
 */
class AutoLoginServicesCest
{
    public function aTokenIsStoredAsItsHashAndRotated(IntegrationTester $I) : void
    {
        $user = $I->createUser('intoken');
        $tokens = $I->grabService(AutoLoginTokenService::class);

        $token = $tokens->issue($user, '127.0.0.1', 'Local', 'Firefox');
        $entity = $tokens->find($token);

        $I->assertNotNull($entity);
        $I->assertSame(hash('sha256', $token), $entity->tokenHash);
        $I->assertNotSame($token, $entity->tokenHash);

        $rotated = $tokens->rotate($entity);
        $I->assertNotSame($token, $rotated);
        $I->assertNull($tokens->find($token), 'The old token still works.');
        $I->assertNotNull($tokens->find($rotated));

        $tokens->revoke($rotated);
        $I->assertNull($tokens->find($rotated));
    }

    public function anExpiredTokenIsDeleted(IntegrationTester $I) : void
    {
        $user = $I->createUser('inexptoken');
        $tokens = $I->grabService(AutoLoginTokenService::class);

        $token = $tokens->issue($user, '127.0.0.1', null, null);
        $entity = $tokens->find($token);
        $I->assertNotNull($entity);
        $entity->validUntil = new DateTimeImmutable('-1 minute');
        $I->em()->flush();

        $I->assertNull($tokens->find($token));
        $I->assertSame(0, $I->em()->getRepository(UserAutoLoginEntity::class)->count(['user' => $user->id]));
    }

    public function theAuthenticatorLetsInOnlyAnActiveUserWithValidToken(IntegrationTester $I) : void
    {
        $user = $I->createUser('inauth', true, 'Normal');
        $tokens = $I->grabService(AutoLoginTokenService::class);
        $authenticator = $I->grabService(AutoLoginAuthenticator::class);

        $identity = $authenticator->authenticate($tokens->issue($user, '127.0.0.1', null, null), '');
        $I->assertSame($user->id, $identity->getId());
        $I->assertSame(['Normal'], $identity->getRoles());

        $I->expectThrowable(AuthenticationException::class, static fn () => $authenticator->authenticate('no-such-token', ''));

        $token = $tokens->issue($user, '127.0.0.1', null, null);
        $user->isActive = false;
        $I->em()->flush();

        $I->expectThrowable(AuthenticationException::class, static fn () => $authenticator->authenticate($token, ''));
        $I->assertSame(0, $I->em()->getRepository(UserAutoLoginEntity::class)->count(['user' => $user->id]), 'An inactive user kept his tokens.');
    }

    public function everyRequestLogsInByTheCookieAndOutTheDeactivatedUser(IntegrationTester $I) : void
    {
        $user = $I->createUser('intrylogin');
        $token = $I->grabService(AutoLoginTokenService::class)->issue($user, '127.0.0.1', null, null);

        $application = $I->grabService(Application::class);
        $application->onPresenter[] = [$I->grabService(AutoLoginService::class), 'tryLogin'];

        $I->setCookie(AutoLoginAuthenticator::COOKIE_NAME, 'not-a-token');
        $I->amOnPage('/web.user.login/');
        $I->dontSee('Již jste přihlášeni.');

        $I->setCookie(AutoLoginAuthenticator::COOKIE_NAME, $token);
        $I->amOnPage('/web.user.login/');
        $I->see('Již jste přihlášeni.');

        $user->isActive = false;
        $I->em()->flush();

        $I->amOnPage('/web.user.login/');
        $I->dontSee('Již jste přihlášeni.');
        $I->assertSame(0, $I->em()->getRepository(UserAutoLoginEntity::class)->count(['user' => $user->id]));
    }
}
