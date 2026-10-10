<?php declare(strict_types=1);

namespace Tests\Functional;

use App\Database\EntityManagerDecorator;
use App\Model\Entity\UserEntity;
use App\Model\Entity\UserPasswordRequestAttemptEntity;
use App\Model\Entity\UserPasswordRequestEntity;
use Nette\Security\Passwords;
use Tests\Support\FunctionalTester;

/**
 * `Admin` and `admin` are one account, `John@x.cz` and `john@x.cz` are one e-mail: the login, the "already exists" checks of the
 * forms, the password reset (e-mails are stored in lower case; the unique check of the username compares lower case, there is no
 * database index on lower(username): DBAL cannot describe an expression index and the project is not deployed with data to convert).
 */
class CaseInsensitiveAccountCest
{
    private const PASSWORD = 'Abcdefgh1234!x';

    public function theLoginDoesNotCareAboutTheCaseOfTheUsername(FunctionalTester $I) : void
    {
        $user = $this->createUser($I, 'CaseLogin');

        $I->amOnPage('/web.user.login/');
        $I->submitForm('#frm-loginForm', ['username' => strtolower($user->username), 'password' => self::PASSWORD]);
        $I->seeResponseCodeIs(200);
        $I->dontSeeInSource('id="tracy-bs"');

        $I->amOnPage('/web.user.login/');
        $I->see('Již jste přihlášeni.');
    }

    public function aUsernameThatDiffersOnlyInCaseIsTaken(FunctionalTester $I) : void
    {
        $user = $this->createUser($I, 'CaseTaken');
        $tag = (string) random_int(100000, 999999);

        $I->amOnPage('/web.user.registration/');
        $I->submitForm('#frm-registrationForm', [
            'name' => 'Reg', 'surname' => 'Test', 'username' => strtoupper($user->username), 'email' => 'other' . $tag . '@example.com',
            'password' => self::PASSWORD, 'password2' => self::PASSWORD,
        ]);
        $I->seeResponseCodeIs(200);

        $I->assertSame(1, $this->countUsers($I, $user->username), 'A second account with the same username in another case was created.');
    }

    public function anEmailThatDiffersOnlyInCaseIsTaken(FunctionalTester $I) : void
    {
        $user = $this->createUser($I, 'CaseMail');
        $tag = (string) random_int(100000, 999999);

        $I->amOnPage('/web.user.registration/');
        $I->submitForm('#frm-registrationForm', [
            'name' => 'Reg', 'surname' => 'Test', 'username' => 'casemailother' . $tag, 'email' => strtoupper($user->email),
            'password' => self::PASSWORD, 'password2' => self::PASSWORD,
        ]);
        $I->seeResponseCodeIs(200);

        $em = $this->em($I);
        $em->clear();
        $I->assertNull($em->getRepository(UserEntity::class)->findOneBy(['username' => 'casemailother' . $tag]), 'An account with the e-mail of another one (other case) was created.');
    }

    public function anEmailIsStoredInLowerCase(FunctionalTester $I) : void
    {
        $tag = (string) random_int(100000, 999999);

        $I->amOnPage('/web.user.registration/');
        $I->submitForm('#frm-registrationForm', [
            'name' => 'Reg', 'surname' => 'Test', 'username' => 'caseshort' . $tag, 'email' => 'Mixed.Case' . $tag . '@Example.COM',
            'password' => self::PASSWORD, 'password2' => self::PASSWORD,
        ]);

        $em = $this->em($I);
        $em->clear();
        $user = $em->getRepository(UserEntity::class)->findOneBy(['username' => 'caseshort' . $tag]);
        $I->assertNotNull($user, 'The registration did not create the user.');
        $I->trackCreatedEntity(UserEntity::class, $user->id);
        $I->assertSame('mixed.case' . $tag . '@example.com', $user->email);
    }

    public function thePasswordResetFindsTheAccountWhateverTheCaseOfTheEmail(FunctionalTester $I) : void
    {
        $user = $this->createUser($I, 'CaseReset');

        $I->amOnPage('/web.user.forget-password.request/');
        $I->submitForm('#frm-requestForm', ['email' => strtoupper($user->email)]);
        $I->seeResponseCodeIs(200);
        $I->dontSeeInSource('id="tracy-bs"');

        $em = $this->em($I);
        $em->clear();
        $I->assertSame(1, $em->getRepository(UserPasswordRequestEntity::class)->count(['user' => $user->id]), 'No reset request was created for the e-mail in another case.');

        $em->createQuery('DELETE FROM ' . UserPasswordRequestAttemptEntity::class . ' a WHERE LOWER(a.email) = :e')->setParameter('e', strtolower($user->email))->execute();
    }

    private function countUsers(FunctionalTester $I, string $username) : int
    {
        $em = $this->em($I);
        $em->clear();

        return (int) $em->createQuery('SELECT COUNT(u.id) FROM ' . UserEntity::class . ' u WHERE LOWER(u.username) = :u')
            ->setParameter('u', strtolower($username))
            ->getSingleScalarResult();
    }

    private function createUser(FunctionalTester $I, string $label) : UserEntity
    {
        $tag = $label . random_int(100000, 999999);
        /** @var Passwords $passwords */
        $passwords = $I->grabService(Passwords::class);
        $em = $this->em($I);

        $user = new UserEntity();
        $user->name = 'Case';
        $user->surname = 'Test';
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
