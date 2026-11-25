<?php declare(strict_types=1);

namespace Tests\Unit;

use Tests\Support\UnitTester;

/**
 * Regression (2026-11-25, found in family-tree): the tom-select of a select filter in the header of a datagrid opened its dropdown
 * inside the grid, where `.table-responsive` (overflow: auto) clipped it - in a short or empty grid the last options could not be
 * seen. The TomSelect of the datagrid's SelectpickerPlugin gets `dropdownParent: 'body'`.
 */
class GridSelectDropdownOnBodyCest
{
    public function theTomSelectOfTheDatagridHangsOnTheBody(UnitTester $I) : void
    {
        $found = 0;

        $paths = glob(dirname(__DIR__, 2) . '/assets/*.js');

        foreach ($paths === false ? [] : $paths as $path) {
            $content = (string) file_get_contents($path);

            foreach (['new SelectpickerPlugin(new TomSelect(Select))', "new SelectpickerPlugin(new TomSelect(Select, {dropdownParent: 'body'}))"] as $index => $call) {
                if (str_contains($content, $call)) {
                    $found++;
                    $I->assertSame(1, $index, basename($path) . ': the dropdown of the datagrid selects is clipped by .table-responsive, give it dropdownParent: \'body\'.');
                }
            }
        }

        $I->assertGreaterThan(0, $found, 'The initialisation of the datagrid (SelectpickerPlugin) was not found in assets/.');
    }
}
