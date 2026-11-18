<?php declare(strict_types=1);

namespace Tests\Integration;

use App\Core\AutoLoginAuthenticator;
use App\Core\AutoLoginTokenService;
use App\Model\Entity\MailEntity;
use App\Model\Entity\UserActivationEntity;
use App\Model\Entity\UserAutoLoginEntity;
use App\Model\Entity\UserEmailChangeRequestEntity;
use App\Model\Entity\UserEmailEntity;
use App\Model\Entity\UserEntity;
use App\Model\Entity\UserPasswordEntity;
use App\Model\Entity\UserPasswordRequestEntity;
use DateTimeImmutable;
use Nette\Security\Passwords;
use Tests\Support\IntegrationTester;

/**
 * The public pages of the account (registration, activation, login, logout, profile, password, e-mail, forgotten password) run
 * through the Nette Application IN the test process (NetteApplicationModule), so `make coverage` measures the presenters and the
 * forms. The same flows over real HTTP are in tests/Functional (UserFormsCest, LoginLogoutCest, ForgetPasswordCest, ...).
 */
class WebUserPagesCest
{
    private const string NEW_PASSWORD = 'Zyxwvuts9876!q';

    public function thePublicPagesRender(IntegrationTester $I) : void
    {
        foreach (['/', '/web.user.login/', '/web.user.registration/', '/web.user.forget-password.request/'] as $url) {
            $I->amOnPage($url);
            $I->seeResponseCodeIs(200);
        }
    }

    public function theRegistrationCreatesAnInactiveUserAndTheLinkActivatesIt(IntegrationTester $I) : void
    {
        $tag = 'inreg' . random_int(100000, 999999);

        $I->amOnPage('/web.user.registration/');
        $I->submitForm('#frm-registrationForm', [
            'name' => 'Reg',
            'surname' => 'Istration',
            'username' => $tag,
            'email' => $tag . '@example.com',
            'password' => IntegrationTester::PASSWORD,
            'password2' => IntegrationTester::PASSWORD,
        ]);
        $I->seeResponseCodeIs(200);

        $em = $I->em();
        $em->clear();
        $user = $em->getRepository(UserEntity::class)->findOneByUsername($tag);
        $I->assertNotNull($user, 'The registration did not create the user.');
        $I->assertFalse($user->isActive);
        $I->assertSame(1, $em->getRepository(UserPasswordEntity::class)->count(['user' => $user->id]));
        $I->assertSame(1, $em->getRepository(UserEmailEntity::class)->count(['user' => $user->id]));
        $I->assertSame(1, $em->getRepository(MailEntity::class)->count(['emailTo' => $tag . '@example.com']), 'No registration mail.');

        $activation = $em->getRepository(UserActivationEntity::class)->findOneBy(['user' => $user->id]);
        $I->assertNotNull($activation);

        $I->amOnPage('/web.user.activation/?userId=' . $user->id . '&key=' . $activation->activationKey);
        $I->seeResponseCodeIs(200);
        $I->see('byl úspěšně aktivován');

        $activated = $I->reload(UserEntity::class, $user->id);
        $I->assertNotNull($activated);
        $I->assertTrue($activated->isActive);
        $I->assertSame(0, $I->em()->getRepository(UserActivationEntity::class)->count(['user' => $user->id]));
    }

    public function theRegistrationRefusesTakenUsernameAndEmail(IntegrationTester $I) : void
    {
        $other = $I->createUser('intaken');

        $I->amOnPage('/web.user.registration/');
        $I->submitForm('#frm-registrationForm', [
            'name' => 'Reg',
            'surname' => 'Taken',
            'username' => strtoupper($other->username),
            'email' => strtoupper($other->email),
            'password' => IntegrationTester::PASSWORD,
            'password2' => IntegrationTester::PASSWORD,
        ]);
        $I->seeResponseCodeIs(200);
        $I->see('už existuje');

        $I->assertSame(1, $I->em()->getRepository(UserEntity::class)->count(['email' => $other->email]));
    }

    public function aWrongOrExpiredActivationKeyIsNotFound(IntegrationTester $I) : void
    {
        $user = $I->createUser('inactivate', false);

        $activation = new UserActivationEntity();
        $activation->user = $user;
        $activation->activationKey = 'expired-key-' . $user->username;
        $activation->validUntil = new DateTimeImmutable('-1 minute');
        $I->em()->persist($activation);
        $I->em()->flush();

        $I->seePageIsRefused('/web.user.activation/?userId=' . $user->id . '&key=wrong-key', 404);
        $I->seePageIsRefused('/web.user.activation/?userId=' . $user->id . '&key=' . $activation->activationKey, 404);
        $I->seePageIsRefused('/web.user.activation/?userId=0&key=' . $activation->activationKey, 404);

        $reloaded = $I->reload(UserEntity::class, $user->id);
        $I->assertNotNull($reloaded);
        $I->assertFalse($reloaded->isActive);
    }

    public function theLoginRefusesWrongPasswordAndInactiveAccount(IntegrationTester $I) : void
    {
        $inactive = $I->createUser('ininactive', false);

        $I->amOnPage('/web.user.login/');
        $I->submitForm('#frm-loginForm', ['username' => $inactive->username, 'password' => 'definitely-wrong']);
        $I->see('Nesprávné uživatelské jméno nebo heslo.');

        $I->submitForm('#frm-loginForm', ['username' => $inactive->username, 'password' => IntegrationTester::PASSWORD]);
        $I->see('Účet není aktivní.');
        $I->dontSee('Úspěšně přihlášen');
    }

    public function stayLoggedInIssuesToken(IntegrationTester $I) : void
    {
        $user = $I->createUser('instay');

        $I->amOnPage('/web.user.login/');
        $I->checkOption('#frm-loginForm-stayLoggedIn');
        $I->submitForm('#frm-loginForm', ['username' => $user->username, 'password' => IntegrationTester::PASSWORD]);
        $I->see('Úspěšně přihlášen');

        $autoLogin = $I->em()->getRepository(UserAutoLoginEntity::class)->findOneBy(['user' => $user->id]);
        $I->assertNotNull($autoLogin, '"Stay logged in" did not issue a token.');
        $I->assertSame('Local', $autoLogin->countryCode);

        $I->amOnPage('/web.user.login/');
        $I->see('Již jste přihlášeni.');
    }

    public function theLogoutRevokesTheTokenOfTheCookie(IntegrationTester $I) : void
    {
        $user = $I->createUser('inlogout');
        $I->login($user->username, IntegrationTester::PASSWORD);

        $token = $I->grabService(AutoLoginTokenService::class)->issue($user, '127.0.0.1', 'Local', null);
        $I->setCookie(AutoLoginAuthenticator::COOKIE_NAME, $token);

        $I->amOnPage('/web.user.logout/');
        $I->seeResponseCodeIs(200);
        $I->seeElement('#frm-loginForm');

        $I->assertSame(0, $I->em()->getRepository(UserAutoLoginEntity::class)->count(['user' => $user->id]), 'The logout left the token.');
        $I->seePageIsRefused('/web.user.change-password/', 403);
    }

    public function theLogoutOfAnAnonymousVisitorShowsTheLogin(IntegrationTester $I) : void
    {
        $I->amOnPage('/web.user.logout/');
        $I->seeResponseCodeIs(200);
        $I->seeElement('#frm-loginForm');
    }

    public function theAccountPagesNeedLogin(IntegrationTester $I) : void
    {
        $I->seePageIsRefused('/web.user.edit/', 403);
        $I->seePageIsRefused('/web.user.change-password/', 403);
        $I->seePageIsRefused('/web.user.change-email/', 403);
    }

    public function theProfileShowsAndSavesTheName(IntegrationTester $I) : void
    {
        $user = $I->createUser('inprofile');
        $I->login($user->username, IntegrationTester::PASSWORD);

        $I->amOnPage('/web.user.edit/');
        $I->seeResponseCodeIs(200);
        $I->assertSame('In', $I->grabValueFrom('#frm-editForm-name'));

        $I->submitForm('#frm-editForm', ['name' => 'Renamed', 'surname' => 'Person']);
        $I->seeResponseCodeIs(200);

        $saved = $I->reload(UserEntity::class, $user->id);
        $I->assertNotNull($saved);
        $I->assertSame('Renamed', $saved->name);
        $I->assertSame('Person', $saved->surname);
        $I->assertNotNull($saved->updatedAt);
    }

    public function theProfileOfUserIsPublicByUuid(IntegrationTester $I) : void
    {
        $user = $I->createUser('inview');

        $I->amOnPage('/web.user.view/default/' . $user->uuid);
        $I->seeResponseCodeIs(200);
        $I->see($user->username);

        $I->seePageIsRefused('/web.user.view/default/00000000-0000-4000-8000-000000000000', 404);
    }

    public function thePasswordChangeChecksTheCurrentAndTheUsedPasswords(IntegrationTester $I) : void
    {
        $user = $this->userWithHistory($I, 'inchpass');
        $I->login($user->username, IntegrationTester::PASSWORD);

        $I->amOnPage('/web.user.change-password/');
        $I->seeResponseCodeIs(200);

        $I->submitForm('#frm-changePasswordForm', ['currentPassword' => 'Wrong1234!xyz', 'password' => self::NEW_PASSWORD, 'password2' => self::NEW_PASSWORD]);
        $I->see('Současné heslo nesouhlasí');

        $I->submitForm('#frm-changePasswordForm', ['currentPassword' => IntegrationTester::PASSWORD, 'password' => IntegrationTester::PASSWORD, 'password2' => IntegrationTester::PASSWORD]);
        $I->see('Heslo už bylo použite');

        $this->assertPassword($I, $user->id, IntegrationTester::PASSWORD);
    }

    public function thePasswordChangeSavesTheNewPasswordAndEndsTheAutoLogins(IntegrationTester $I) : void
    {
        $user = $this->userWithHistory($I, 'inchpassok');
        $I->grabService(AutoLoginTokenService::class)->issue($user, '127.0.0.1', 'Local', null);
        $I->login($user->username, IntegrationTester::PASSWORD);

        $I->amOnPage('/web.user.change-password/');
        $I->submitForm('#frm-changePasswordForm', ['currentPassword' => IntegrationTester::PASSWORD, 'password' => self::NEW_PASSWORD, 'password2' => self::NEW_PASSWORD]);
        $I->seeResponseCodeIs(200);

        $this->assertPassword($I, $user->id, self::NEW_PASSWORD);
        $I->assertSame(2, $I->em()->getRepository(UserPasswordEntity::class)->count(['user' => $user->id]));
        $I->assertSame(0, $I->em()->getRepository(UserAutoLoginEntity::class)->count(['user' => $user->id]));
    }

    public function theEmailChangeAsksForThePasswordAndSendsTheLink(IntegrationTester $I) : void
    {
        $user = $I->createUser('inchmail');
        $I->login($user->username, IntegrationTester::PASSWORD);
        $newEmail = 'new.' . strtolower($user->username) . '@example.com';

        $I->amOnPage('/web.user.change-email/');
        $I->seeResponseCodeIs(200);

        $I->submitForm('#frm-changeEmailForm', ['currentPassword' => 'Wrong1234!xyz', 'email' => $newEmail]);
        $I->see('Současné heslo nesouhlasí');
        $I->assertNull($this->emailChangeRequest($I, $user->id));

        $I->submitForm('#frm-changeEmailForm', ['currentPassword' => IntegrationTester::PASSWORD, 'email' => strtoupper($user->email)]);
        $I->assertNull($this->emailChangeRequest($I, $user->id), 'The same address created a request.');

        $I->amOnPage('/web.user.change-email/');
        $I->submitForm('#frm-changeEmailForm', ['currentPassword' => IntegrationTester::PASSWORD, 'email' => $newEmail]);
        $I->seeResponseCodeIs(200);

        $request = $this->emailChangeRequest($I, $user->id);
        $I->assertNotNull($request, 'No change request was created.');
        $I->assertSame($newEmail, $request->newEmail);

        $mails = $I->em()->getRepository(MailEntity::class)->findBy(['emailTo' => $newEmail]);
        $I->assertCount(1, $mails, 'The new address got no link.');
        $I->assertStringContainsString('[token]', $mails[0]->body, 'The log of the mails holds the token.');
        $I->assertSame(1, $I->em()->getRepository(MailEntity::class)->count(['emailTo' => $user->email]), 'The old address was not warned.');
    }

    public function theLinkOfTheNewAddressChangesTheEmailOnce(IntegrationTester $I) : void
    {
        $user = $I->createUser('inconfirm');
        $oldEmail = $user->email;
        $newEmail = 'confirmed.' . strtolower($user->username) . '@example.com';
        $token = $this->emailChangeRequestWithToken($I, $user, $newEmail, new DateTimeImmutable('+1 hour'));

        $I->amOnPage('/web.user.confirm-email/?userId=' . $user->id . '&key=' . str_repeat('0', 64));
        $I->seeResponseCodeIs(200);
        $this->assertEmail($I, $user->id, $oldEmail);

        $I->amOnPage('/web.user.confirm-email/?userId=' . $user->id . '&key=' . $token);
        $I->seeResponseCodeIs(200);
        $this->assertEmail($I, $user->id, $newEmail);
        $I->assertNull($this->emailChangeRequest($I, $user->id));
        $I->assertSame(1, $I->em()->getRepository(UserEmailEntity::class)->count(['user' => $user->id, 'email' => $newEmail]));
        $I->assertSame(1, $I->em()->getRepository(MailEntity::class)->count(['emailTo' => $oldEmail]), 'The old address was not told.');

        $I->amOnPage('/web.user.confirm-email/?userId=' . $user->id . '&key=' . $token);
        $this->assertEmail($I, $user->id, $newEmail);
    }

    public function anExpiredOrTakenEmailChangeChangesNothing(IntegrationTester $I) : void
    {
        $user = $I->createUser('inexpired');
        $other = $I->createUser('inexpiredother');
        $expired = $this->emailChangeRequestWithToken($I, $user, 'expired.' . strtolower($user->username) . '@example.com', new DateTimeImmutable('-1 minute'));

        $I->amOnPage('/web.user.confirm-email/?userId=' . $user->id . '&key=' . $expired);
        $this->assertEmail($I, $user->id, $user->email);
        $I->assertNull($this->emailChangeRequest($I, $user->id), 'The expired request stayed.');

        $taken = $this->emailChangeRequestWithToken($I, $user, $other->email, new DateTimeImmutable('+1 hour'));

        $I->amOnPage('/web.user.confirm-email/?userId=' . $user->id . '&key=' . $taken);
        $this->assertEmail($I, $user->id, $user->email);
        $I->assertNull($this->emailChangeRequest($I, $user->id), 'The request for a taken address stayed.');
    }

    public function theForgottenPasswordRequestSendsTheLinkOnlyToKnownAddress(IntegrationTester $I) : void
    {
        $user = $I->createUser('inforget');

        $I->amOnPage('/web.user.forget-password.request/');
        $I->submitForm('#frm-requestForm', ['email' => strtoupper($user->email)]);
        $I->see('Pokud tento e-mail existuje v naší databázi');

        $I->assertSame(1, $I->em()->getRepository(UserPasswordRequestEntity::class)->count(['user' => $user->id]));
        $I->assertSame(1, $I->em()->getRepository(MailEntity::class)->count(['emailTo' => $user->email]));

        $unknown = 'nobody-' . random_int(100000, 999999) . '@example.com';
        $I->amOnPage('/web.user.forget-password.request/');
        $I->submitForm('#frm-requestForm', ['email' => $unknown]);
        $I->see('Pokud tento e-mail existuje v naší databázi');
        $I->assertSame(0, $I->em()->getRepository(MailEntity::class)->count(['emailTo' => $unknown]));
    }

    public function theForgottenPasswordLinkSetsNewPassword(IntegrationTester $I) : void
    {
        $user = $this->userWithHistory($I, 'inreset');
        $key = $this->passwordRequest($I, $user, new DateTimeImmutable('+1 hour'));
        $url = '/web.user.forget-password.change/?userId=' . $user->id . '&forgetKey=' . $key;

        $I->amOnPage($url);
        $I->seeResponseCodeIs(200);
        $I->see('Nastavení nového hesla');

        $I->submitForm('#frm-changePasswordForm', ['password' => IntegrationTester::PASSWORD, 'password2' => IntegrationTester::PASSWORD]);
        $I->see('Heslo už bylo použite');

        $I->submitForm('#frm-changePasswordForm', ['password' => self::NEW_PASSWORD, 'password2' => self::NEW_PASSWORD]);
        $I->seeResponseCodeIs(200);
        $I->seeElement('#frm-loginForm');

        $this->assertPassword($I, $user->id, self::NEW_PASSWORD);
        $I->assertSame(0, $I->em()->getRepository(UserPasswordRequestEntity::class)->count(['user' => $user->id]));

        $I->login($user->username, self::NEW_PASSWORD);
    }

    public function aWrongOrExpiredForgottenPasswordLinkIsRefused(IntegrationTester $I) : void
    {
        $user = $I->createUser('inresetexp');
        $key = $this->passwordRequest($I, $user, new DateTimeImmutable('-1 minute'));

        $I->seePageIsRefused('/web.user.forget-password.change/?userId=' . $user->id . '&forgetKey=wrong', 404);
        $I->seePageIsRefused('/web.user.forget-password.change/?userId=' . $user->id . '&forgetKey=' . $key, 403);
        $I->assertSame(0, $I->em()->getRepository(UserPasswordRequestEntity::class)->count(['user' => $user->id]), 'The expired request stayed.');
    }

    private function userWithHistory(IntegrationTester $I, string $label) : UserEntity
    {
        $user = $I->createUser($label);

        $password = new UserPasswordEntity();
        $password->user = $user;
        $password->password = $user->password;
        $user->addUserPasswordEntity($password);

        $I->em()->persist($password);
        $I->em()->flush();

        return $user;
    }

    private function assertPassword(IntegrationTester $I, int $userId, string $password) : void
    {
        $user = $I->reload(UserEntity::class, $userId);
        $I->assertNotNull($user);
        $I->assertTrue($I->grabService(Passwords::class)->verify($password, $user->password), 'The password of the user is not ' . $password);
    }

    private function assertEmail(IntegrationTester $I, int $userId, string $email) : void
    {
        $user = $I->reload(UserEntity::class, $userId);
        $I->assertNotNull($user);
        $I->assertSame($email, $user->email);
    }

    private function emailChangeRequest(IntegrationTester $I, int $userId) : ?UserEmailChangeRequestEntity
    {
        $em = $I->em();
        $em->clear();

        return $em->getRepository(UserEmailChangeRequestEntity::class)->findOneBy(['user' => $userId]);
    }

    /**
     * A pending e-mail change of the user; returns its token (the link of the mail, the database keeps its hash).
     */
    private function emailChangeRequestWithToken(IntegrationTester $I, UserEntity $user, string $newEmail, DateTimeImmutable $validUntil) : string
    {
        $em = $I->em();
        $token = bin2hex(random_bytes(32));

        $owner = $em->find(UserEntity::class, $user->id);
        $I->assertNotNull($owner);

        $request = new UserEmailChangeRequestEntity();
        $request->user = $owner;
        $request->newEmail = $newEmail;
        $request->tokenHash = hash('sha256', $token);
        $request->validUntil = $validUntil;

        $em->persist($request);
        $em->flush();

        return $token;
    }

    private function passwordRequest(IntegrationTester $I, UserEntity $user, DateTimeImmutable $validUntil) : string
    {
        $request = new UserPasswordRequestEntity();
        $request->user = $user;
        $request->forgetKey = 'in-process-key-' . $user->username;
        $request->validUntil = $validUntil;

        $I->em()->persist($request);
        $I->em()->flush();

        return $request->forgetKey;
    }
}
