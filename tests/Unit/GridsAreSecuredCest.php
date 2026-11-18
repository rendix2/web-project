<?php declare(strict_types=1);

namespace Tests\Unit;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\Support\UnitTester;

/**
 * Every grid is the project's SecuredDatagrid: a plain `new Datagrid()` asks its row conditions (`allowRowsAction()`,
 * `allowRowsInlineEdit()`) only when it draws the buttons, the signals run for any id sent by hand (GridSignalIdsCest and the
 * row permission tests). The same guard in alfred, web-project and family-tree.
 *
 * The class after `new` is resolved through the `use` imports of the file, so `use ...\SecuredDatagrid as DataGrid;` +
 * `new DataGrid()` is fine and `use Contributte\Datagrid\Datagrid as Grid;` + `new Grid()` is not. Importing the library class for
 * a type hint is fine.
 */
class GridsAreSecuredCest
{
    private const array LIBRARY_CLASSES = ['contributte\datagrid\datagrid', 'ublaboo\datagrid\datagrid'];

    public function noGridIsPlainDatagrid(UnitTester $I) : void
    {
        $offenders = [];
        $scanned = 0;
        $root = dirname(__DIR__, 2);

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/app', RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
            if (!$file instanceof SplFileInfo || $file->getExtension() !== 'php') {
                continue;
            }

            $scanned++;
            $source = (string) file_get_contents($file->getPathname());

            foreach ($this->instantiatedClasses($source) as $class) {
                if (in_array(strtolower($class), self::LIBRARY_CLASSES, true)) {
                    $offenders[] = substr($file->getPathname(), strlen($root) + 1) . ': new ' . $class;
                }
            }
        }

        $I->assertGreaterThan(10, $scanned, 'The scan did not find the application.');
        $I->assertSame([], $offenders, "Use new SecuredDatagrid() instead of the library datagrid:\n" . implode("\n", $offenders));
    }

    /**
     * The fully qualified names of the classes the source creates with `new Name(`.
     *
     * @return list<string>
     */
    private function instantiatedClasses(string $source) : array
    {
        $namespace = preg_match('~^\s*namespace\s+([\w\\\\]+)\s*;~m', $source, $match) === 1 ? $match[1] : '';
        $aliases = [];

        if (preg_match_all('~^\s*use\s+([\w\\\\]+)(?:\s+as\s+(\w+))?\s*;~m', $source, $uses, PREG_SET_ORDER) > 0) {
            foreach ($uses as $use) {
                $parts = explode('\\', $use[1]);
                $alias = $use[2] ?? end($parts); // PREG_SET_ORDER leaves an unmatched "as" group out
                $aliases[strtolower($alias)] = $use[1];
            }
        }

        $classes = [];

        if (preg_match_all('~\bnew\s+(\\\\?[\w\\\\]+)\s*\(~', $source, $news) > 0) {
            foreach ($news[1] as $name) {
                if (str_starts_with($name, '\\')) {
                    $classes[] = substr($name, 1);

                    continue;
                }

                $first = strtolower(explode('\\', $name)[0]);
                $rest = substr($name, strlen($first));

                $classes[] = isset($aliases[$first])
                    ? $aliases[$first] . $rest
                    : ($namespace === '' ? $name : $namespace . '\\' . $name);
            }
        }

        return $classes;
    }
}
