<?php declare(strict_types=1);

namespace Tests\Functional;

use App\Database\EntityManagerDecorator;
use App\Model\Entity\RoleEntity;
use App\Model\Entity\UserEntity;
use Codeception\Example;
use PHPUnit\Framework\Assert;
use Tests\Support\Grid\DatagridPage;
use Tests\Support\FunctionalTester;

/**
 * Exercises EVERY datagrid of the admin like a user does - every sort link, every filter (with a value that matches nothing, with
 * junk, with a real option), hiding/showing every column, the per-page choices, resetting - over real HTTP, and asserts what has
 * to hold whatever the markup or the datagrid version is: the page answers 200 without a Tracy screen, sorting only reorders,
 * a filter only narrows (and a value that matches nothing really empties the grid: the filter condition callbacks run),
 * reset gives the original rows back.
 *
 * @group grid
 *
 * It exists as the safety net for the Bootstrap 5 + datagrid update: the markup knowledge is in Tests\Support\Grid\DatagridPage.
 */
class GridExerciserCest
{
    private const NOTHING = 'zzqq-no-such-value-';

    /** @var array<string, int> */
    private array $logSizes = [];

    public function _before(FunctionalTester $I) : void
    {
        $this->logSizes = $this->currentLogSizes();
    }

    public function _after(FunctionalTester $I) : void
    {
        $problems = [];

        foreach ($this->currentLogSizes() as $file => $size) {
            $before = $this->logSizes[$file] ?? 0;

            if ($size > $before) {
                $problems[] = basename(dirname($file)) . '/' . basename($file) . ":\n" . substr((string) file_get_contents($file, false, null, $before), 0, 1500);
            }
        }

        // the datagrid catches errors of its own rendering/queries: the page still answers 200 (just without the grid), only the log knows
        if ($problems !== []) {
            Assert::fail("The test wrote to the exception/error log:\n" . implode("\n", $problems));
        }
    }

    /**
     * @return array<string, int>
     */
    private function currentLogSizes() : array
    {
        clearstatcache();
        $root = dirname(__DIR__, 2) . '/log';
        $sizes = [];

        foreach (array_merge(glob($root . '/exception.log') ?: [], glob($root . '/error.log') ?: [], glob($root . '/*/exception.log') ?: [], glob($root . '/*/error.log') ?: []) as $file) {
            if (!str_contains($file, '/codeception/')) {
                $sizes[$file] = (int) filesize($file);
            }
        }

        return $sizes;
    }

    /**
     * @return array<string, array{0: string}>
     */
    protected function pagesProvider() : array
    {
        $urls = ['/admin.user.list/', '/admin.role.list/', '/admin.user.edit/default/{user}', '/admin.role.edit/default/{role}'];

        $examples = [];

        foreach ($urls as $url) {
            $examples[$url] = [$url];
        }

        return $examples;
    }

    /**
     * @dataProvider pagesProvider
     */
    public function everyGridOfThePageBehaves(FunctionalTester $I, Example $example) : void
    {
        $I->amOnPage('/web.user.login/');
        $I->submitForm('#frm-loginForm', ['username' => 'TestUsername', 'password' => 'secret']);
        $I->seeResponseCodeIs(200);

        $url = $this->resolve($I, $example[0]);
        $I->amOnPage($url);
        $this->assertPageIsFine($I);

        $names = (new DatagridPage($I->grabPageSource()))->gridNames();
        if ($names === [] && $this->mayHaveNoGrid($url)) {
            return;
        }

        Assert::assertNotSame([], $names, 'No datagrid found on ' . $url . ' - the adapter or the page changed.');

        foreach ($names as $grid) {
            $this->exerciseSorting($I, $url, $grid);
            $this->exerciseFilters($I, $url, $grid);
            $this->exerciseColumns($I, $url, $grid);
            $this->exercisePerPage($I, $url, $grid);
        }
    }

    private function mayHaveNoGrid(string $url) : bool
    {
        return str_contains($url, '/admin.role.edit/');
    }

    private function exerciseSorting(FunctionalTester $I, string $url, string $grid) : void
    {
        $I->amOnPage($url);
        $page = new DatagridPage($I->grabPageSource());
        $baseIds = $page->rowIds($grid);
        $complete = $page->totalItems($grid) === count($baseIds);

        foreach ($page->sortUrls($grid) as $column => $ascUrl) {
            $I->amOnPage($ascUrl);
            $this->assertPageIsFine($I, $grid . ' sort ' . $column . ' asc');
            $asc = $this->gridOf($I, $grid, $grid . ' sort ' . $column . ' asc');

            $I->amOnPage(str_replace('%5D=ASC', '%5D=DESC', str_replace(']=ASC', ']=DESC', $ascUrl)));
            $this->assertPageIsFine($I, $grid . ' sort ' . $column . ' desc');
            $desc = $this->gridOf($I, $grid, $grid . ' sort ' . $column . ' desc');

            if (!$complete) {
                continue; // only the first page is shown, the sorted pages hold other rows
            }

            $ascIds = $asc->rowIds($grid);
            $descIds = $desc->rowIds($grid);

            $this->assertSameSet($baseIds, $ascIds, $grid . ': sorting by ' . $column . ' (asc) changed the set of rows');
            $this->assertSameSet($baseIds, $descIds, $grid . ': sorting by ' . $column . ' (desc) changed the set of rows');

            $values = $asc->cells($grid, $column);

            if (count($values) > 1 && count(array_unique($values)) === count($values) && !in_array('', $values, true)) {
                Assert::assertSame(array_reverse($ascIds), $descIds, $grid . ': desc is not the reverse of asc when sorting by ' . $column);
            }
        }

        $I->amOnPage($url);
    }

    private function exerciseFilters(FunctionalTester $I, string $url, string $grid) : void
    {
        $I->amOnPage($url);
        $page = new DatagridPage($I->grabPageSource());
        $baseIds = $page->rowIds($grid);

        foreach ($page->filters($grid) as $filter) {
            $label = $grid . ' filter ' . $filter['key'] . ' (' . $filter['kind'] . ')';

            $values = match ($filter['kind']) {
                'select' => array_slice($filter['options'], 0, 3),
                'multiselect' => [[$filter['options'][0] ?? '']],
                'date' => ['1. 1. 2000'],
                'range' => [['from' => 'zzqq', 'to' => 'zzqq'], ['from' => '1', 'to' => '1000']],
                default => [self::NOTHING . random_int(100000, 999999)],
            };

            if ($filter['kind'] === 'multiselect' && ($filter['options'][1] ?? null) !== null) {
                $values[] = [$filter['options'][0], $filter['options'][1]];
            }

            foreach ($values as $value) {
                if ($value === '' || $value === ['']) {
                    continue;
                }

                $I->amOnPage($url);
                $I->submitForm('#' . $page->filterFormId($grid), ['filter' => [$filter['key'] => $value]]);
                $this->assertPageIsFine($I, $label . ' = ' . json_encode($value));

                $filtered = $this->gridOf($I, $grid, $label);
                $ids = $filtered->rowIds($grid);

                if ($filter['kind'] === 'text') {
                    Assert::assertSame([], $ids, $label . ': a value that matches nothing still shows rows (the filter condition is not applied)');
                }

                if (count($baseIds) > 0 && count($baseIds) === $page->totalItems($grid)) {
                    Assert::assertSame([], array_values(array_diff($ids, $baseIds)), $label . ' shows rows that were not there without it');
                }

                // reset: the original rows are back
                $resetUrl = $filtered->resetColumnFilterUrl($grid, $filter['key']);
                Assert::assertNotNull($resetUrl, $label . ': no "reset filter" link of the column');
                $I->amOnPage($resetUrl);
                $this->assertPageIsFine($I, $label . ' reset');
                Assert::assertSame($baseIds, $this->gridOf($I, $grid, $label . ' reset')->rowIds($grid), $label . ': resetting the filter did not bring the rows back');
            }

            if ($filter['kind'] === 'date') {
                // junk in a date filter must not take the page down
                $I->amOnPage($url);
                $I->submitForm('#' . $page->filterFormId($grid), ['filter' => [$filter['key'] => 'not a date']]);
                $this->assertPageIsFine($I, $label . ' = junk');
                $junk = $this->gridOf($I, $grid, $label . ' = junk');

                if ($junk->resetColumnFilterUrl($grid, $filter['key']) !== null) {
                    $I->amOnPage($junk->resetColumnFilterUrl($grid, $filter['key']));
                }
            }
        }

        $I->amOnPage($url);
    }

    private function exerciseColumns(FunctionalTester $I, string $url, string $grid) : void
    {
        $I->amOnPage($url);
        $page = new DatagridPage($I->grabPageSource());
        $before = $page->columns($grid);
        $hideUrls = $page->hideColumnUrls($grid);

        foreach ($hideUrls as $column => $hideUrl) {
            $I->amOnPage($hideUrl);
            $this->assertPageIsFine($I, $grid . ' hide ' . $column);
            $hidden = $this->gridOf($I, $grid, $grid . ' hide ' . $column);

            Assert::assertNotContains($column, $hidden->columns($grid), $grid . ': column ' . $column . ' is still shown after hiding it');
            Assert::assertArrayHasKey($column, $hidden->showColumnUrls($grid), $grid . ': no "show" link for the hidden column ' . $column);

            $I->amOnPage($hidden->showColumnUrls($grid)[$column]);
            $this->assertPageIsFine($I, $grid . ' show ' . $column);
        }

        $I->amOnPage($url);
        Assert::assertSame($before, (new DatagridPage($I->grabPageSource()))->columns($grid), $grid . ': hiding and showing every column changed the columns');
    }

    private function exercisePerPage(FunctionalTester $I, string $url, string $grid) : void
    {
        $I->amOnPage($url);
        $page = new DatagridPage($I->grabPageSource());
        $options = $page->perPageOptions($grid);

        if ($options === []) {
            return;
        }

        $default = $page->rowIds($grid);

        // "all" on a grid with thousands of rows is minutes of rendering: only try it where the grid is small
        $small = ($page->totalItems($grid) ?? 0) <= 200;

        foreach ($options as $option) {
            if ($option === 'all' && !$small) {
                continue;
            }

            $I->amOnPage($url);
            $I->submitForm('#' . $page->filterFormId($grid), ['perPage' => $option]);
            $this->assertPageIsFine($I, $grid . ' perPage ' . $option);

            $count = count((new DatagridPage($I->grabPageSource()))->rowIds($grid));

            if (ctype_digit($option)) {
                Assert::assertLessThanOrEqual((int) $option, $count, $grid . ': perPage ' . $option . ' shows more rows');
            }
        }

        if (!$small) {
            return;
        }

        // "all" shows at least the rows of the default page size
        $I->amOnPage($url);
        $I->submitForm('#' . $page->filterFormId($grid), ['perPage' => 'all']);
        Assert::assertGreaterThanOrEqual(count($default), count((new DatagridPage($I->grabPageSource()))->rowIds($grid)));
    }

    /**
     * The grid of the CURRENT page; fails with a clear message when the page lost it (an error inside the grid is swallowed)
     */
    private function gridOf(FunctionalTester $I, string $grid, string $what) : DatagridPage
    {
        $page = new DatagridPage($I->grabPageSource());
        Assert::assertTrue($page->hasGrid($grid), 'The page lost the datagrid "' . $grid . '" (' . $what . ') - an error inside the grid was swallowed.');

        return $page;
    }

    private function assertPageIsFine(FunctionalTester $I, string $what = '') : void
    {
        $I->seeResponseCodeIs(200);
        $source = $I->grabPageSource();

        if (str_contains($source, 'id="tracy-bs"')) {
            $title = preg_match('~<title>(.*?)</title>~s', $source, $m) === 1 ? html_entity_decode(trim($m[1])) : '?';
            Assert::fail('Tracy exception screen' . ($what !== '' ? ' (' . $what . ')' : '') . ': ' . substr($title, 0, 700));
        }

        Assert::assertStringNotContainsString('Nette\\Application\\BadRequestException', $source, $what);
    }

    /**
     * @param list<string> $expected
     * @param list<string> $actual
     */
    private function assertSameSet(array $expected, array $actual, string $message) : void
    {
        sort($expected);
        sort($actual);

        Assert::assertSame($expected, $actual, $message);
    }

    private function resolve(FunctionalTester $I, string $url) : string
    {
        /** @var EntityManagerDecorator $em */
        $em = $I->grabService(EntityManagerDecorator::class);

        $url = str_replace('{user}', (string) $em->getRepository(UserEntity::class)->findOneBy(['username' => 'TestUsername'])?->uuid, $url);

        return str_replace('{role}', (string) $em->getRepository(RoleEntity::class)->findOneBy([], ['id' => 'ASC'])?->id, $url);
    }
}
