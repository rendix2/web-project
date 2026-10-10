<?php declare(strict_types=1);

namespace Tests\Functional;

use App\Database\EntityManagerDecorator;
use App\Model\Entity\UserEntity;
use Codeception\Example;
use PHPUnit\Framework\Assert;
use Tests\Support\FunctionalTester;

/**
 * The "Edit" link of a datagrid inline edit has to be an AJAX link (class `ajax`; the datagrid's own default is `btn btn-xs ... ajax`, and
 * `setClass()` REPLACES it). Without it the link is a plain page load, which renders only the edited row: all the other rows vanish from
 * the grid as soon as someone clicks "Edit" (seen in Alfred's Karma, 2026-10-04). Browser check: Acceptance/InlineEditKeepsRowsCest.
 */
class InlineEditAjaxCest
{
    /**
     * @return array<string, array{0: string}>
     */
    protected function pagesProvider() : array
    {
        $urls = ['/admin.user.list/', '/admin.role.list/', '/admin.user.edit/default/{user}'];
        $examples = [];

        foreach ($urls as $url) {
            $examples[$url] = [$url];
        }

        return $examples;
    }

    /**
     * @dataProvider pagesProvider
     */
    public function inlineEditLinksAreAjax(FunctionalTester $I, Example $example) : void
    {
        $I->amOnPage('/web.user.login/');
        $I->submitForm('#frm-loginForm', ['username' => 'TestUsername', 'password' => 'secret']);

        /** @var EntityManagerDecorator $em */
        $em = $I->grabService(EntityManagerDecorator::class);
        $user = $em->getRepository(UserEntity::class)->findOneBy(['username' => 'TestUsername']);
        $I->assertNotNull($user);

        $I->amOnPage(str_replace('{user}', (string) $user->uuid, $example[0]));
        $I->seeResponseCodeIs(200);

        preg_match_all('~<a\b[^>]*\bdatagrid-inline-edit-trigger\b[^>]*>~', $I->grabPageSource(), $links);

        $plain = array_values(array_filter($links[0], static fn (string $link): bool => preg_match('~\bclass="[^"]*\bajax\b~', $link) !== 1));

        Assert::assertSame([], $plain, 'Inline edit links of ' . $example[0] . ' are not AJAX links (add "ajax" to setClass()).');
    }

    public function userListHasInlineEditLinks(FunctionalTester $I) : void
    {
        $I->amOnPage('/web.user.login/');
        $I->submitForm('#frm-loginForm', ['username' => 'TestUsername', 'password' => 'secret']);
        $I->amOnPage('/admin.user.list/');

        $I->seeInSource('datagrid-inline-edit-trigger');
    }
}
