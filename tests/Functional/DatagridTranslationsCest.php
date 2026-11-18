<?php declare(strict_types=1);

namespace Tests\Functional;

use Codeception\Attribute\DataProvider;
use Codeception\Example;
use Symfony\Component\DomCrawler\Crawler;
use Tests\Support\FunctionalTester;

/**
 * contributte/datagrid 7 renamed its translation keys from ublaboo_datagrid.* to contributte_datagrid.*; a missing translation file
 * or key shows up as the raw key in the grid (footer, filters, buttons). From family-tree (2026-11-18): every grid page shows its
 * texts in Czech, no raw key. Only the rendered page is checked - the Tracy bar (debug mode) dumps untranslated objects.
 */
class DatagridTranslationsCest
{
    /**
     * @return array<string, array{0: string}>
     */
    protected function pagesProvider() : array
    {
        $examples = [];

        foreach (['/admin.user.list/', '/admin.role.list/'] as $url) {
            $examples[$url] = [$url];
        }

        return $examples;
    }

    /**
     * @param Example<int, string> $example
     */
    #[DataProvider('pagesProvider')]
    public function theGridShowsTranslatedTextsNotRawKeys(FunctionalTester $I, Example $example) : void
    {
        $url = $example[0];
        assert(is_string($url));

        $I->loginAs(FunctionalTester::ROLE_ADMIN);
        $I->amOnPage($url);
        $I->seePageIsOk($url);

        $grids = (new Crawler($I->grabPageSource()))->filter('.datagrid');
        $I->assertGreaterThan(0, $grids->count(), $url . ' has no datagrid.');

        $text = '';

        foreach ($grids as $grid) {
            $text .= $grid->ownerDocument?->saveHTML($grid);
        }

        $I->assertDoesNotMatchRegularExpression('~\b(?:contributte|ublaboo)_datagrid\.\w+~', $text, $url . ': a datagrid text is not translated.');
        $I->assertStringContainsString('Položky', $text, $url . ': the footer is not in Czech.');
    }
}
