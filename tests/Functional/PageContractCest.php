<?php declare(strict_types=1);

namespace Tests\Functional;

use App\Database\EntityManagerDecorator;
use App\Model\Entity\RoleEntity;
use App\Model\Entity\UserEntity;
use Codeception\Attribute\DataProvider;
use Codeception\Example;
use Tests\Support\FunctionalTester;
use Tests\Support\Grid\PageContract;

/**
 * Golden files (tests/Support/Data/page-contracts/*.json) of what every page promises to the user - see Tests\Support\Grid\PageContract.
 * Meant for updates of Bootstrap, the datagrid and the templates: the markup may change, the contract may not. A changed label or a
 * lost control is an explicit, reviewed update of the golden file:
 * `UPDATE_PAGE_CONTRACTS=1 php vendor/bin/codecept run Functional PageContractCest` (after `make db`: the contracts read the
 * fixtures). A page without a golden file writes it on the first run. The pages are read as the role that sees the most of them.
 */
class PageContractCest
{
    /**
     * @return array<string, array{0: string, 1: string}> url, role
     */
    protected function pagesProvider() : array
    {
        $pages = [
            '/' => FunctionalTester::ROLE_ANONYMOUS,
            '/web.user.login/' => FunctionalTester::ROLE_ANONYMOUS,
            '/web.user.registration/' => FunctionalTester::ROLE_ANONYMOUS,
            '/web.user.forget-password.request/' => FunctionalTester::ROLE_ANONYMOUS,
            '/web.user.view/default/{user}' => FunctionalTester::ROLE_ADMIN,
            '/web.user.edit/' => FunctionalTester::ROLE_ADMIN,
            '/web.user.change-password/' => FunctionalTester::ROLE_ADMIN,
            '/web.user.change-email/' => FunctionalTester::ROLE_ADMIN,
            '/admin.user.list/' => FunctionalTester::ROLE_ADMIN,
            '/admin.user.create/' => FunctionalTester::ROLE_ADMIN,
            '/admin.user.edit/default/{user}' => FunctionalTester::ROLE_ADMIN,
            '/admin.role.list/' => FunctionalTester::ROLE_ADMIN,
            '/admin.role.create/' => FunctionalTester::ROLE_ADMIN,
            '/admin.role.edit/default/{role}' => FunctionalTester::ROLE_ADMIN,
        ];

        $examples = [];

        foreach ($pages as $url => $role) {
            $examples[$url] = [$url, $role];
        }

        return $examples;
    }

    /**
     * @param Example<int, string> $example
     */
    #[DataProvider('pagesProvider')]
    public function pageKeepsItsContract(FunctionalTester $I, Example $example) : void
    {
        $page = $example[0];
        $role = $example[1];
        assert(is_string($page) && is_string($role));

        $I->loginAs($role);
        $I->amOnPage($this->resolve($I, $page));
        $I->seePageIsOk($page);

        $contract = (new PageContract($I->grabPageSource()))->toArray();
        $name = trim((string) preg_replace('~[^a-z0-9]+~i', '-', $page), '-');
        $file = dirname(__DIR__) . '/Support/Data/page-contracts/' . ($name === '' ? 'home' : $name) . '.json';
        $json = json_encode($contract, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";

        if (getenv('UPDATE_PAGE_CONTRACTS') === '1' || !is_file($file)) {
            if (!is_dir(dirname($file))) {
                mkdir(dirname($file), 0777, true);
            }

            file_put_contents($file, $json);
            $I->comment('Contract written to ' . $file);

            return;
        }

        $I->assertSame((string) file_get_contents($file), $json, 'The contract of ' . $page . ' changed (' . basename($file) . ').');
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
