<?php declare(strict_types=1);

namespace Tests\Unit;

use Tests\Support\UnitTester;

/**
 * The navigation is written by hand in the layouts (like in the sister projects), there is no menu package.
 */
class NoMenuPackageCest
{
    public function composerDoesNotRequireAMenuPackage(UnitTester $I) : void
    {
        $composer = (string) file_get_contents(dirname(__DIR__, 2) . '/composer.json');

        $I->assertStringNotContainsString('menu-control', $composer);
    }

    public function nothingReferencesTheMenuPackage(UnitTester $I) : void
    {
        $found = [];
        $needle = 'MenuC' . 'ontrol';

        foreach (['app', 'config'] as $dir) {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(dirname(__DIR__, 2) . '/' . $dir, \FilesystemIterator::SKIP_DOTS)) as $file) {
                if (str_contains((string) file_get_contents($file->getPathname()), $needle)) {
                    $found[] = $file->getPathname();
                }
            }
        }

        $I->assertSame([], $found, "The menu package is still referenced:\n" . implode("\n", $found));
    }

}
