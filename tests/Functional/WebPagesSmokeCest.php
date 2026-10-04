<?php declare(strict_types=1);

namespace Tests\Functional;

use App\Database\EntityManagerDecorator;
use App\Model\Entity\RoleEntity;
use App\Model\Entity\UserEntity;
use Codeception\Example;
use Tests\Support\FunctionalTester;

/**
 * Smoke test of every server-rendered page: it has to answer 200 and must not render a Tracy exception. A plain
 * HTTP check on purpose - the Acceptance (Selenium) tests drive the UI, but they do not notice a page that answers
 * 500/404 behind a flash message or a redirect. `{user}` / `{role}` are replaced by the id of an existing record.
 */
class WebPagesSmokeCest
{
    /**
     * @return array<string, array{0: string}>
     */
    protected function publicPagesProvider() : array
    {
        return $this->examples([
            '/',
            '/web.user.login/',
            '/web.user.registration/',
            '/web.user.forget-password.request/',
        ]);
    }

    /**
     * @return array<string, array{0: string}>
     */
    protected function loggedInPagesProvider() : array
    {
        return $this->examples([
            '/web.user.edit/',
            '/web.user.change-password/',
            '/web.user.change-email/',
            '/web.user.view/default/{user}',
            '/admin.user.list/',
            '/admin.user.create/',
            '/admin.user.edit/default/{user}',
            '/admin.role.list/',
            '/admin.role.create/',
            '/admin.role.edit/default/{role}',
        ]);
    }

    /**
     * @dataProvider publicPagesProvider
     */
    public function publicPageRendersWithoutError(FunctionalTester $I, Example $example) : void
    {
        $I->amOnPage($example[0]);

        $this->assertPageIsOk($I);
    }

    /**
     * @dataProvider loggedInPagesProvider
     */
    public function loggedInPageRendersWithoutError(FunctionalTester $I, Example $example) : void
    {
        $I->amOnPage('/web.user.login/');
        $I->submitForm('#frm-loginForm', ['username' => 'TestUsername', 'password' => 'secret']);
        $I->seeResponseCodeIs(200);

        $I->amOnPage($this->resolve($I, $example[0]));

        $this->assertPageIsOk($I);
    }

    private function assertPageIsOk(FunctionalTester $I) : void
    {
        $I->seeResponseCodeIs(200);
        $I->dontSeeInSource('id="tracy-bs"');
        $I->dontSeeInSource('Nette\\Application\\BadRequestException');
    }

    private function resolve(FunctionalTester $I, string $url) : string
    {
        /** @var EntityManagerDecorator $em */
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

    /**
     * @param list<string> $urls
     * @return array<string, array{0: string}>
     */
    private function examples(array $urls) : array
    {
        $examples = [];

        foreach ($urls as $url) {
            $examples[$url] = [$url];
        }

        return $examples;
    }
}
