<?php declare(strict_types=1);

namespace Tests\Unit;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\Support\UnitTester;

/**
 * The tests use the attributes of Codeception (Codeception\Attribute\Group, Skip, DataProvider ...), never the docblock
 * annotations: a typo in an annotation (@grup, @dataprovider) is silently ignored, a typo in an attribute is an error and
 * PHPStan checks its arguments. (Same test in alfred and family-tree.)
 */
class TestsUseAttributesCest
{
    /** the annotations Codeception has an attribute for (Codeception\Attribute\*) */
    private const array ANNOTATIONS = [
        'after',
        'before',
        'dataProvider',
        'depends',
        'env',
        'example',
        'examples',
        'given',
        'group',
        'incomplete',
        'prepare',
        'skip',
        'then',
        'when',
    ];

    /** generated or produced by the runs, not written by hand */
    private const array SKIPPED_DIRECTORIES = ['/tests/Support/_generated/', '/tests/_output/', '/tests/Support/_output/'];

    public function noTestUsesAnAnnotationInsteadOfAnAttribute(UnitTester $I) : void
    {
        $root = dirname(__DIR__, 2) . '/';
        $pattern = '~^\s*\*\s*@(' . implode('|', self::ANNOTATIONS) . ')\b~mi';
        $found = [];

        foreach ($this->phpFiles($root . 'tests') as $path) {
            $lines = file($path);

            if ($lines === false) {
                $I->fail('Cannot read ' . $path);
            }

            foreach ($lines as $number => $line) {
                if (preg_match($pattern, $line, $match) === 1) {
                    $found[] = str_replace($root, '', $path) . ':' . ($number + 1) . ' @' . $match[1];
                }
            }
        }

        $I->assertSame([], $found, 'Use #[Codeception\Attribute\...] instead of the annotation.');
    }

    /**
     * @return list<string>
     */
    private function phpFiles(string $directory) : array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS));

        foreach ($iterator as $file) {
            if (!$file instanceof SplFileInfo || $file->getExtension() !== 'php') {
                continue;
            }

            $path = str_replace('\\', '/', $file->getPathname());

            foreach (self::SKIPPED_DIRECTORIES as $skipped) {
                if (str_contains($path, $skipped)) {
                    continue 2;
                }
            }

            $files[] = $path;
        }

        sort($files);

        return $files;
    }
}
