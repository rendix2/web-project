<?php declare(strict_types=1);

namespace Tests\Unit;

use Tests\Support\UnitTester;

/**
 * An action button of a datagrid is as wide as its caption. `w-100` made the "Smazat" button fill (and overflow) the whole cell of
 * the Actions column, next to the narrow "Detail" button.
 */
class GridActionButtonsCest
{
    public function noGridActionButtonFillsTheWholeCell(UnitTester $I) : void
    {
        $wrong = [];
        $checked = 0;

        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(dirname(__DIR__, 2) . '/app/UI', \FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $content = (string) file_get_contents($file->getPathname());

            if (preg_match_all('/->setClass\(([^)]*)\)/', $content, $matches) > 0) {
                foreach ($matches[1] as $classes) {
                    $checked++;

                    if (preg_match('/\b(w-100|btn-block|d-block)\b/', $classes) === 1) {
                        $wrong[] = substr($file->getPathname(), strlen(dirname(__DIR__, 2)) + 1) . ': ' . $classes;
                    }
                }
            }
        }

        $I->assertGreaterThan(0, $checked, 'The scan did not find the grid buttons.');
        $I->assertSame([], $wrong, "Grid buttons that fill the whole cell:\n" . implode("\n", $wrong));
    }

}
