<?php declare(strict_types=1);

namespace Tests\Support;

use App\Database\EntityManagerDecorator;
use App\Model\Entity\RoleEntity;
use App\Model\Entity\UserEntity;
use Codeception\Actor;
use Nette\Application\BadRequestException;
use Nette\Security\Passwords;

/**
 * Inherited Methods
 *
 * @method void wantTo($text)
 * @method void wantToTest($text)
 * @method void execute($callable)
 * @method void expectTo($prediction)
 * @method void expect($prediction)
 * @method void amGoingTo($argumentation)
 * @method void am($role)
 * @method void lookForwardTo($achieveValue)
 * @method void comment($description)
 * @method void pause($vars = [])
 * @SuppressWarnings(PHPMD)
 */
class IntegrationTester extends Actor
{
    use _generated\IntegrationTesterActions;

    /** the password of the users made by createUser() */
    public const string PASSWORD = 'Abcdefgh1234!x';

    /**
     * The fixture admin TestUsername logs in (the login form of the application, in the test process).
     */
    public function loginAsAdmin() : void
    {
        $this->login('TestUsername', 'secret');
    }

    public function login(string $username, string $password) : void
    {
        $this->amOnPage('/web.user.login/');
        $this->submitForm('#frm-loginForm', ['username' => $username, 'password' => $password]);
        $this->seeResponseCodeIs(200);
        $this->see('Úspěšně přihlášen');
    }

    /**
     * An account for the test (password PASSWORD, removed after the test by CleanupTestData), optionally with a fixture role.
     */
    public function createUser(string $label, bool $active = true, ?string $role = null) : UserEntity
    {
        $em = $this->em();
        $tag = $label . random_int(100000, 999999);

        $user = new UserEntity();
        $user->name = 'In';
        $user->surname = 'Process';
        $user->username = $tag;
        $user->email = strtolower($tag) . '@example.com';
        $user->password = $this->grabService(Passwords::class)->hash(self::PASSWORD);
        $user->isActive = $active;

        if ($role !== null) {
            $roleEntity = $em->getRepository(RoleEntity::class)->findOneBy(['name' => $role]);
            $this->assertNotNull($roleEntity, 'The fixture role ' . $role . ' is missing.');
            $user->addRoleEntity($roleEntity);
        }

        $em->persist($user);
        $em->flush();
        $this->trackCreatedEntity(UserEntity::class, $user->id);

        return $user;
    }

    /**
     * The page is refused with the code: in the test process (debug mode, so no catchExceptions) the BadRequestException of
     * `$presenter->error()` comes out of the application itself.
     */
    public function seePageIsRefused(string $url, int $code) : void
    {
        try {
            $this->amOnPage($url);
        } catch (BadRequestException $exception) {
            // InnerBrowser turns the following of redirects off for the request and back on only when the request returns
            $this->startFollowingRedirects();
            $this->assertSame($code, $exception->getCode(), $url . ': ' . $exception->getMessage());

            return;
        }

        $this->fail($url . ' was not refused with ' . $code . '.');
    }

    /**
     * The entity again from the database: the EntityManager is shared with the application of the test, clear() forgets what the
     * requests left in it.
     *
     * @template T of object
     * @param class-string<T> $class
     * @return T|null
     */
    public function reload(string $class, mixed $id) : ?object
    {
        $em = $this->em();
        $em->clear();

        return $em->find($class, $id);
    }

    public function em() : EntityManagerDecorator
    {
        return $this->grabService(EntityManagerDecorator::class);
    }
}
