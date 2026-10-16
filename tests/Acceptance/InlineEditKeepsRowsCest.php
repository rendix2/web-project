<?php declare(strict_types=1);

namespace Tests\Acceptance;

use Codeception\Util\Locator;
use App\Database\EntityManagerDecorator;
use App\Model\Entity\RoleEntity;
use Codeception\Example;
use PHPUnit\Framework\Assert;
use Tests\Support\AcceptanceTester;

/**
 * Clicking "Edit" (and saving) on one row of a grid must not make the other rows disappear. The cause was an inline edit link without the
 * `ajax` class: a plain page load renders only the edited row (see Functional/InlineEditAjaxCest, which checks the markup of the grids).
 */
class InlineEditKeepsRowsCest
{
    /**
     * @return array<string, array{0: string}>
     */
    protected function pagesProvider() : array
    {
        // the user list has a single fixture user (the edit link of its grid is checked by Functional/InlineEditAjaxCest)
        return ['/admin.role.list/' => ['/admin.role.list/']];
    }

    /**
     * @dataProvider pagesProvider
     */
    public function otherRowsStayWhenOneRowIsEdited(AcceptanceTester $I, Example $example) : void
    {
        $rows = 'return document.querySelectorAll("table tbody tr[data-id]").length;';
        $editing = 'document.querySelectorAll("table tbody tr[data-id] [name^=\'inline_edit\']").length';

        // a grid with a single row proves nothing: make sure there are a few
        /** @var EntityManagerDecorator $em */
        $em = $I->grabService(EntityManagerDecorator::class);

        $created = [];

        for ($i = 0; $i < 2; $i++) {
            $role = new RoleEntity();
            $created[] = $role;
            $role->name = 'InlineEditRole' . random_int(100000, 999999);
            $role->description = 'inline edit test';
            $em->persist($role);
        }

        $em->flush();

        try {
            $I->resizeWindow(1400, 1000);
            $I->amOnPage('/web.user.login/');
            $I->fillField('username', 'TestUsername');
            $I->fillField('password', 'secret');
            $I->click('#frm-loginForm [type=submit]');
            $I->wait(1);
            $I->amOnPage($example[0]);
            $I->waitForElement('table tbody tr[data-id]', 10);

            $before = (int) $I->executeJS($rows);
            Assert::assertGreaterThan(1, $before, 'The grid needs at least two rows for this check.');

            $I->executeJS('document.getElementById("tracy-debug")?.remove(); document.documentElement.style.scrollBehavior = "auto";');
            $I->scrollTo(Locator::firstElement('table > tbody > tr[data-id]'));
            $I->click(Locator::firstElement('table > tbody > tr[data-id] a.datagrid-inline-edit-trigger'));
            $I->waitForJS('return ' . $editing . ' > 0;', 10);
            Assert::assertSame($before, (int) $I->executeJS($rows), 'Clicking "Edit" removed rows from the grid.');

            $I->click(Locator::find('*', ['name' => 'inline_edit[submit]']));
            $I->waitForJS('return ' . $editing . ' === 0;', 10);
            Assert::assertSame($before, (int) $I->executeJS($rows), 'Saving the row removed the other rows from the grid.');
            $I->dontSeeElement('#tracy-bs');
        } finally {
            foreach ($created as $role) {
                $em->remove($em->getRepository(RoleEntity::class)->find($role->id) ?? $role);
            }

            $em->flush();
        }
    }
}
