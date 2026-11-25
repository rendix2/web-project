<?php declare(strict_types=1);

namespace Tests\Functional;

use App\Database\EntityManagerDecorator;
use App\Model\Entity\UserEntity;
use App\Model\Entity\UserPasswordRequestAttemptEntity;
use App\Model\Entity\UserPasswordRequestEntity;
use DateTimeImmutable;
use Nette\Security\Passwords;
use Tests\Support\FunctionalTester;

/**
 * Password reset flow, submitted the way the browser does it. Covers the bugs found/fixed
 * 2026-09-28: email enumeration via the request form, a DB failure still emailing a dead link,
 * missing rate limiting, and the "set new password" page's empty translations.
 *
 * The request form is rate limited per email/IP (UserPasswordRequestAttemptCheckService) and its
 * attempt rows are created by the web server, not this test's own EntityManager, so
 * CleanupTestData cannot auto-track them. Every address a test submits goes through attemptsOf()
 * and its attempts are deleted in _after() AND _failed(): a test that failed half way used to leave
 * them behind, and as all Functional tests come from one IP, the next run was blocked for 15 minutes.
 */
class ForgetPasswordCest
{
    private const NEW_PASSWORD = 'Abcdefgh1234!x';

    /** @var list<string> the addresses whose attempts the test leaves behind */
    private array $attemptEmails;

    public function _before() : void
    {
        $this->attemptEmails = [];
    }

    public function _after(FunctionalTester $I) : void
    {
        $this->deleteAttempts($I);
    }

    public function _failed(FunctionalTester $I) : void
    {
        $this->deleteAttempts($I);
    }

    public function requestingResetForAnExistingEmailShowsTheGenericMessage(FunctionalTester $I) : void
    {
        /** @var EntityManagerDecorator $em */
        $em = $I->grabService(EntityManagerDecorator::class);
        $userEntity = $this->createActiveUser($I, $em, 'existing');

        $I->amOnPage('/web.user.forget-password.request/');
        $I->submitForm('#frm-requestForm', ['email' => $this->attemptsOf($userEntity->email)]);
        $I->seeResponseCodeIs(200);
        $I->dontSeeInSource('id="tracy-bs"');
        $I->see('Pokud tento e-mail existuje v naší databázi');

        $em->clear();
        $request = $em->getRepository(UserPasswordRequestEntity::class)->findOneBy(['user' => $userEntity->id]);
        $I->assertNotNull($request, 'No UserPasswordRequestEntity was created for a known email.');
    }

    /**
     * The exact same message for a non-existent email is the whole point - a different response
     * here would let anyone enumerate registered emails through this form.
     */
    public function requestingResetForAnUnknownEmailShowsTheSameGenericMessage(FunctionalTester $I) : void
    {
        $email = 'definitely-not-a-real-user-' . random_int(100000, 999999) . '@example.com';

        $I->amOnPage('/web.user.forget-password.request/');
        $I->submitForm('#frm-requestForm', ['email' => $this->attemptsOf($email)]);
        $I->seeResponseCodeIs(200);
        $I->dontSeeInSource('id="tracy-bs"');
        $I->see('Pokud tento e-mail existuje v naší databázi');
    }

    public function repeatedRequestsForTheSameEmailAreRateLimited(FunctionalTester $I) : void
    {
        $email = 'throttle-test-' . random_int(100000, 999999) . '@example.com';

        for ($i = 0; $i < 7; $i++) {
            $I->amOnPage('/web.user.forget-password.request/');
            $I->submitForm('#frm-requestForm', ['email' => $this->attemptsOf($email)]);
            $I->seeResponseCodeIs(200);
        }

        /** @var EntityManagerDecorator $em */
        $em = $I->grabService(EntityManagerDecorator::class);
        $em->clear();

        $count = $em->getRepository(UserPasswordRequestAttemptEntity::class)
            ->count(['email' => $email]);

        $I->assertSame(5, $count, 'More than the configured 5 attempts/900s were logged - rate limiting is not capping requests.');
    }

    public function completingTheResetFlowLetsTheUserLoginWithTheNewPassword(FunctionalTester $I) : void
    {
        /** @var EntityManagerDecorator $em */
        $em = $I->grabService(EntityManagerDecorator::class);
        $userEntity = $this->createActiveUser($I, $em, 'reset');

        $requestEntity = new UserPasswordRequestEntity();
        $requestEntity->user = $userEntity;
        $requestEntity->forgetKey = 'functional-test-key-' . $userEntity->username;
        $requestEntity->validUntil = new DateTimeImmutable('+1 day');

        $em->persist($requestEntity);
        $em->flush();

        $I->amOnPage('/web.user.forget-password.change/?userId=' . $userEntity->id . '&forgetKey=' . $requestEntity->forgetKey);
        $I->seeResponseCodeIs(200);
        $I->dontSeeInSource('id="tracy-bs"');
        $I->see('Nastavení nového hesla');

        $I->submitForm('#frm-changePasswordForm', [
            'password' => self::NEW_PASSWORD,
            'password2' => self::NEW_PASSWORD,
        ]);
        $I->seeResponseCodeIs(200);
        $I->dontSeeInSource('id="tracy-bs"');

        $I->amOnPage('/web.user.login/');
        $I->submitForm('#frm-loginForm', ['username' => $userEntity->username, 'password' => self::NEW_PASSWORD]);
        $I->seeResponseCodeIs(200);
        $I->dontSeeInSource('id="tracy-bs"');
        $I->see('Úspěšně přihlášen');
    }

    public function anExpiredResetLinkIsRejected(FunctionalTester $I) : void
    {
        /** @var EntityManagerDecorator $em */
        $em = $I->grabService(EntityManagerDecorator::class);
        $userEntity = $this->createActiveUser($I, $em, 'expired');

        $requestEntity = new UserPasswordRequestEntity();
        $requestEntity->user = $userEntity;
        $requestEntity->forgetKey = 'functional-test-expired-key-' . $userEntity->username;
        $requestEntity->validUntil = new DateTimeImmutable('-1 day');

        $em->persist($requestEntity);
        $em->flush();

        // Debug mode is always on in this project (see AGENTS.md "Known caveats"), so Tracy's
        // bluescreen reports every BadRequestException as HTTP 500 regardless of the code passed
        // to $this->error() - the intended 403 only shows up in the page title/content.
        $I->amOnPage('/web.user.forget-password.change/?userId=' . $userEntity->id . '&forgetKey=' . $requestEntity->forgetKey);
        $I->seeResponseCodeIs(500);
        $I->see('expirovaný');
    }

    private function createActiveUser(FunctionalTester $I, EntityManagerDecorator $em, string $label) : UserEntity
    {
        $tag = $label . random_int(100000, 999999);
        $passwords = $I->grabService(Passwords::class);

        $userEntity = new UserEntity();
        $userEntity->name = 'Reset';
        $userEntity->surname = 'Test';
        $userEntity->username = $tag;
        $userEntity->email = $tag . '@example.com';
        $userEntity->password = $passwords->hash('original-password-x1');
        $userEntity->isActive = true;

        $em->persist($userEntity);
        $em->flush();
        $I->trackCreatedEntity(UserEntity::class, $userEntity->id);

        return $userEntity;
    }

    /**
     * Registers the address BEFORE the form is submitted, so its attempts are deleted however the test ends.
     */
    private function attemptsOf(string $email) : string
    {
        if (!in_array($email, $this->attemptEmails, true)) {
            $this->attemptEmails[] = $email;
        }

        return $email;
    }

    /**
     * UserPasswordRequestAttemptEntity rows come from the web server (form submits), not this
     * test's own EntityManager, so CleanupTestData cannot auto-track/delete them.
     */
    private function deleteAttempts(FunctionalTester $I) : void
    {
        if ($this->attemptEmails === []) {
            return;
        }

        $I->grabService(EntityManagerDecorator::class)
            ->createQueryBuilder()
            ->delete(UserPasswordRequestAttemptEntity::class, '_attempt')
            ->where('_attempt.email IN (:emails)')
            ->setParameter('emails', $this->attemptEmails)
            ->getQuery()
            ->execute();

        $this->attemptEmails = [];
    }
}
