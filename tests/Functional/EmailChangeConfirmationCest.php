<?php declare(strict_types=1);

namespace Tests\Functional;

use App\Database\EntityManagerDecorator;
use App\Model\Entity\MailEntity;
use App\Model\Entity\UserEmailChangeRequestEntity;
use App\Model\Entity\UserEmailEntity;
use App\Model\Entity\UserEntity;
use DateTimeImmutable;
use Nette\Security\Passwords;
use Tests\Support\FunctionalTester;

/**
 * Changing the e-mail of an account: the new address is NOT saved by the form. It gets a confirmation link (the token is only hashed
 * in the database), the old address gets a warning, and the e-mail changes when the link of the NEW address is opened. Without that
 * anybody who holds a session (or a stolen password) could redirect the password reset of the account to his own mailbox.
 */
class EmailChangeConfirmationCest
{
    private const PASSWORD = 'Abcdefgh1234!x';

    /** @var list<string> */
    private array $newEmails;

    public function _before(FunctionalTester $I) : void
    {
        $this->newEmails = [];
    }

    public function _after(FunctionalTester $I) : void
    {
        $em = $this->em($I);
        $em->clear();

        foreach ($this->newEmails as $email) {
            $em->createQuery('DELETE FROM ' . MailEntity::class . ' m WHERE m.emailTo = :e')->setParameter('e', $email)->execute();
            $em->createQuery('DELETE FROM ' . UserEmailEntity::class . ' e WHERE e.email = :e')->setParameter('e', $email)->execute();
        }
    }

    public function theFormDoesNotChangeTheEmailButSendsTheConfirmationAndTheWarning(FunctionalTester $I) : void
    {
        $user = $this->createUserAndLogin($I, 'ecform');
        $newEmail = $this->newEmail($user);

        $this->submitChange($I, $newEmail, self::PASSWORD);

        $I->seeResponseCodeIs(200);
        $I->dontSeeInSource('id="tracy-bs"');

        $I->assertSame($user->email, $this->reload($I, $user)->email, 'The form changed the e-mail without a confirmation.');

        $request = $this->request($I, $user);
        $I->assertNotNull($request, 'No change request was created.');
        $I->assertSame($newEmail, $request->newEmail);

        $toNew = $this->mailTo($I, $newEmail);
        $I->assertNotNull($toNew, 'The confirmation was not sent to the NEW address.');
        $I->assertStringContainsString('web.user.confirm-email/', $toNew->body);
        $I->assertDoesNotMatchRegularExpression('~key=[0-9a-f]{64}~', $toNew->body, 'The log of the mails holds a working confirmation token.');
        $I->assertMatchesRegularExpression('~web\.user\.confirm-email/\?userId=' . $user->id . '&(?:amp;)?key=[0-9a-f]{64}~', $this->sentMail($newEmail), 'The mail that was sent holds no link.');

        $toOld = $this->mailTo($I, $user->email);
        $I->assertNotNull($toOld, 'The OLD address was not warned.');
        $I->assertStringContainsString($newEmail, $toOld->body);
        $I->assertStringNotContainsString('confirm-email', $toOld->body, 'The warning to the old address must not hold the confirmation link.');
    }

    public function theTokenIsStoredOnlyAsAHash(FunctionalTester $I) : void
    {
        $user = $this->createUserAndLogin($I, 'echash');
        $newEmail = $this->newEmail($user);

        $this->submitChange($I, $newEmail, self::PASSWORD);

        $key = $this->keyFromMail($I, $newEmail);
        $request = $this->request($I, $user);
        $I->assertNotNull($request);
        $I->assertNotSame($key, $request->tokenHash);
        $I->assertSame(hash('sha256', $key), $request->tokenHash);
    }

    public function theLinkOfTheNewAddressChangesTheEmail(FunctionalTester $I) : void
    {
        $user = $this->createUserAndLogin($I, 'ecconfirm');
        $oldEmail = $user->email;
        $newEmail = $this->newEmail($user);

        $this->submitChange($I, $newEmail, self::PASSWORD);
        $key = $this->keyFromMail($I, $newEmail);

        $I->amOnPage('/web.user.confirm-email/?userId=' . $user->id . '&key=' . $key);
        $I->seeResponseCodeIs(200);
        $I->dontSeeInSource('id="tracy-bs"');

        $I->assertSame($newEmail, $this->reload($I, $user)->email);
        $I->assertNull($this->request($I, $user), 'The used request stayed.');
        $I->assertSame(1, $this->em($I)->getRepository(UserEmailEntity::class)->count(['user' => $user->id, 'email' => $newEmail]), 'The new e-mail is not in the history.');

        // the old address is told that it happened
        $I->assertCount(2, $this->mailsTo($I, $oldEmail), 'The old address was not told that the e-mail changed.');

        // the link works once
        $I->amOnPage('/web.user.confirm-email/?userId=' . $user->id . '&key=' . $key);
        $I->assertSame($newEmail, $this->reload($I, $user)->email);
    }

    public function aWrongOrExpiredKeyChangesNothing(FunctionalTester $I) : void
    {
        $user = $this->createUserAndLogin($I, 'ecwrong');
        $newEmail = $this->newEmail($user);

        $this->submitChange($I, $newEmail, self::PASSWORD);
        $key = $this->keyFromMail($I, $newEmail);

        $I->amOnPage('/web.user.confirm-email/?userId=' . $user->id . '&key=' . str_repeat('0', 64));
        $I->dontSeeInSource('id="tracy-bs"');
        $I->assertSame($user->email, $this->reload($I, $user)->email);

        $request = $this->request($I, $user);
        $I->assertNotNull($request);
        $request->validUntil = new DateTimeImmutable('-1 minute');
        $this->em($I)->flush();

        $I->amOnPage('/web.user.confirm-email/?userId=' . $user->id . '&key=' . $key);
        $I->dontSeeInSource('id="tracy-bs"');
        $I->assertSame($user->email, $this->reload($I, $user)->email, 'An expired link changed the e-mail.');
    }

    public function anAddressThatWasTakenInTheMeantimeIsRefused(FunctionalTester $I) : void
    {
        $user = $this->createUserAndLogin($I, 'ectaken');
        $newEmail = $this->newEmail($user);

        $this->submitChange($I, $newEmail, self::PASSWORD);
        $key = $this->keyFromMail($I, $newEmail);

        // somebody registers with it before the link is opened
        $other = $this->createUser($I, 'ectakenother');
        $this->em($I)->createQuery('UPDATE ' . UserEntity::class . ' u SET u.email = :e WHERE u.id = :id')->setParameter('e', $newEmail)->setParameter('id', $other->id)->execute();

        $I->amOnPage('/web.user.confirm-email/?userId=' . $user->id . '&key=' . $key);
        $I->dontSeeInSource('id="tracy-bs"');

        $I->assertSame($user->email, $this->reload($I, $user)->email);
    }

    public function aNewRequestReplacesThePreviousOne(FunctionalTester $I) : void
    {
        $user = $this->createUserAndLogin($I, 'ecreplace');
        $first = $this->newEmail($user, 'first');
        $second = $this->newEmail($user, 'second');

        $this->submitChange($I, $first, self::PASSWORD);
        $firstKey = $this->keyFromMail($I, $first);
        $this->submitChange($I, $second, self::PASSWORD);

        $I->assertSame(1, $this->em($I)->getRepository(UserEmailChangeRequestEntity::class)->count(['user' => $user->id]));

        $I->amOnPage('/web.user.confirm-email/?userId=' . $user->id . '&key=' . $firstKey);
        $I->assertSame($user->email, $this->reload($I, $user)->email, 'The link of the replaced request still works.');
    }

    public function aWrongPasswordDoesNotCreateARequest(FunctionalTester $I) : void
    {
        $user = $this->createUserAndLogin($I, 'ecpass');
        $newEmail = $this->newEmail($user);

        $this->submitChange($I, $newEmail, 'definitely-wrong-Password-1');

        $I->assertNull($this->request($I, $user));
        $I->assertNull($this->mailTo($I, $newEmail));
    }

    public function anAddressOfAnotherAccountIsRefused(FunctionalTester $I) : void
    {
        $user = $this->createUserAndLogin($I, 'ecdup');
        $other = $this->createUser($I, 'ecdupother');

        $this->submitChange($I, strtoupper($other->email), self::PASSWORD);

        $I->assertNull($this->request($I, $user), 'A request for an address that belongs to another account was created.');
    }

    private function submitChange(FunctionalTester $I, string $email, string $password) : void
    {
        $I->amOnPage('/web.user.change-email/');
        $I->submitForm('#frm-changeEmailForm', ['currentPassword' => $password, 'email' => $email]);
    }

    private function newEmail(UserEntity $user, string $suffix = 'new') : string
    {
        $email = $suffix . '.' . strtolower($user->username) . '@example.com';
        $this->newEmails[] = $email;

        return $email;
    }

    private function request(FunctionalTester $I, UserEntity $user) : ?UserEmailChangeRequestEntity
    {
        $em = $this->em($I);
        $em->clear();

        return $em->getRepository(UserEmailChangeRequestEntity::class)->findOneBy(['user' => $user->id]);
    }

    private function reload(FunctionalTester $I, UserEntity $user) : UserEntity
    {
        $em = $this->em($I);
        $em->clear();
        $fresh = $em->find(UserEntity::class, $user->id);
        $I->assertNotNull($fresh);

        return $fresh;
    }

    private function mailTo(FunctionalTester $I, string $email) : ?MailEntity
    {
        $mails = $this->mailsTo($I, $email);

        return $mails === [] ? null : $mails[count($mails) - 1];
    }

    /**
     * @return list<MailEntity>
     */
    private function mailsTo(FunctionalTester $I, string $email) : array
    {
        $em = $this->em($I);
        $em->clear();

        return array_values($em->getRepository(MailEntity::class)->findBy(['emailTo' => $email], ['id' => 'ASC']));
    }

    private function keyFromMail(FunctionalTester $I, string $email) : string
    {
        $I->assertSame(1, preg_match('~key=([0-9a-f]{64})~', $this->sentMail($email), $m), 'No confirmation link was sent to ' . $email);

        return $m[1];
    }

    /**
     * The newest mail to the address as it was sent (the dev mailer writes every mail to a file; the log table holds no token).
     */
    private function sentMail(string $email) : string
    {
        $files = glob(dirname(__DIR__, 2) . '/temp/web/mails/*') ?: [];
        usort($files, static fn (string $a, string $b) : int => filemtime($b) <=> filemtime($a) ?: strcmp($b, $a));

        foreach ($files as $file) {
            $content = (string) file_get_contents($file);

            if (preg_match('~^To:[^\r\n]*' . preg_quote($email, '~') . '~mi', $content) === 1) {
                return $content;
            }
        }

        return '';
    }

    private function createUserAndLogin(FunctionalTester $I, string $label) : UserEntity
    {
        $user = $this->createUser($I, $label);

        $I->amOnPage('/web.user.login/');
        $I->submitForm('#frm-loginForm', ['username' => $user->username, 'password' => self::PASSWORD]);
        $I->seeResponseCodeIs(200);

        return $user;
    }

    private function createUser(FunctionalTester $I, string $label) : UserEntity
    {
        $tag = $label . random_int(100000, 999999);
        /** @var Passwords $passwords */
        $passwords = $I->grabService(Passwords::class);
        $em = $this->em($I);

        $user = new UserEntity();
        $user->name = 'Mail';
        $user->surname = 'Change';
        $user->username = $tag;
        $user->email = strtolower($tag) . '@example.com';
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
