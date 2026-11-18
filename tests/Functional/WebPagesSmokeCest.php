<?php declare(strict_types=1);

namespace Tests\Functional;

use App\Database\EntityManagerDecorator;
use App\Model\Entity\RoleEntity;
use App\Model\Entity\UserEntity;
use Codeception\Attribute\DataProvider;
use Codeception\Example;
use Tests\Support\FunctionalTester;

/**
 * Smoke test of every server-rendered page for every role: nobody logged in, a plain user (role Normal) and an admin. Each page
 * either renders (200, no Tracy exception) or is refused with the expected code (401 / 403) - never a 500, never shown to whom it
 * is not meant for. A plain HTTP check on purpose: the Acceptance (Selenium) tests drive the UI, but they do not see the status
 * code. `{user}` / `{role}` are replaced by the id of an existing record. A new page belongs into PAGES.
 */
class WebPagesSmokeCest
{
    private const OK = 200;

    /**
     * url => expected answer per role (200 = rendered, otherwise the code it is refused with)
     *
     * @var array<string, array{anonymous: int, User: int, Admin: int}>
     */
    private const PAGES = [
        '/' => ['anonymous' => self::OK, 'User' => self::OK, 'Admin' => self::OK],
        '/web.user.login/' => ['anonymous' => self::OK, 'User' => self::OK, 'Admin' => self::OK],
        '/web.user.registration/' => ['anonymous' => self::OK, 'User' => self::OK, 'Admin' => self::OK],
        '/web.user.forget-password.request/' => ['anonymous' => self::OK, 'User' => self::OK, 'Admin' => self::OK],
        // the public profile of a user
        '/web.user.view/default/{user}' => ['anonymous' => self::OK, 'User' => self::OK, 'Admin' => self::OK],

        '/web.user.edit/' => ['anonymous' => 403, 'User' => self::OK, 'Admin' => self::OK],
        '/web.user.change-password/' => ['anonymous' => 403, 'User' => self::OK, 'Admin' => self::OK],
        '/web.user.change-email/' => ['anonymous' => 403, 'User' => self::OK, 'Admin' => self::OK],

        // AdminBasePresenter: 401 without a login, 403 without the role Admin
        '/admin.user.list/' => ['anonymous' => 401, 'User' => 403, 'Admin' => self::OK],
        '/admin.user.create/' => ['anonymous' => 401, 'User' => 403, 'Admin' => self::OK],
        '/admin.user.edit/default/{user}' => ['anonymous' => 401, 'User' => 403, 'Admin' => self::OK],
        '/admin.role.list/' => ['anonymous' => 401, 'User' => 403, 'Admin' => self::OK],
        '/admin.role.create/' => ['anonymous' => 401, 'User' => 403, 'Admin' => self::OK],
        '/admin.role.edit/default/{role}' => ['anonymous' => 401, 'User' => 403, 'Admin' => self::OK],
    ];

    /**
     * @return array<string, array{0: string, 1: string, 2: int}> url, role, expected answer
     */
    protected function pagesProvider() : array
    {
        $examples = [];

        foreach (self::PAGES as $url => $answers) {
            foreach ($answers as $role => $answer) {
                $examples[$role . ' ' . $url] = [$url, $role, $answer];
            }
        }

        return $examples;
    }

    /**
     * @param Example<int, string|int> $example
     */
    #[DataProvider('pagesProvider')]
    public function pageAnswersAsExpectedForTheRole(FunctionalTester $I, Example $example) : void
    {
        $page = $example[0];
        $role = $example[1];
        $answer = $example[2];
        assert(is_string($page) && is_string($role) && is_int($answer));

        $I->loginAs($role);
        $I->amOnPage($this->resolve($I, $page));

        if ($answer === self::OK) {
            $I->seePageIsOk($role . ' ' . $page);
        } else {
            $I->seeRefused($answer, $role . ' ' . $page);
        }
    }

    private function resolve(FunctionalTester $I, string $url) : string
    {
        $em = $I->grabService(EntityManagerDecorator::class);

        if (str_contains($url, '{user}')) {
            $user = $em->getRepository(UserEntity::class)->findOneBy(['username' => 'TestUsername']);
            $I->assertNotNull($user, 'The TestUsername user is missing in the database.');
            $url = str_replace('{user}', (string) $user->uuid, $url);
        }

        if (str_contains($url, '{role}')) {
            $role = $em->getRepository(RoleEntity::class)->findOneBy([], ['id' => 'ASC']);
            $I->assertNotNull($role, 'No role in the database.');
            $url = str_replace('{role}', (string) $role->id, $url);
        }

        return $url;
    }
}
