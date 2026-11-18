<?php declare(strict_types=1);

namespace Tests\Unit;

use Tests\Support\UnitTester;

/**
 * No static methods and no traits in the application (AGENTS.md "No static calls, never" and "No traits"): every behaviour is an
 * instance method of a service that is constructor-injected, shared code is a collaborator or a parent class, never a trait. The
 * router factory is a normal service (`@routerFactory::create()`), Bootstrap is created by `new Bootstrap()` in the entry points.
 *
 * A static CLOSURE (`static fn () => ...`) is no static method and is fine. There is no allowed exception now: a method that a
 * library interface really requires to be static would get an explicit list of allowed files here, each with the reason.
 */
class NoStaticMethodsOrTraitsCest
{
    private const array FORBIDDEN = [
        '~\bstatic\s+function\s+&?\w~' => 'a static method',
        '~^[ \t]+use\s+\\\\?[A-Za-z_][\w\\\\]*\s*(?:,\s*\\\\?[A-Za-z_][\w\\\\]*\s*)*[;{]~m' => 'a trait used in a class',
        '~^[ \t]*trait\s+\w~m' => 'a trait',
    ];

    public function theApplicationHasNoStaticMethodsAndNoTraits(UnitTester $I) : void
    {
        $directory = dirname(__DIR__, 2) . '/app';
        $found = [];
        $scanned = 0;

        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS));

        foreach ($files as $file) {
            if (!$file instanceof \SplFileInfo || $file->getExtension() !== 'php') {
                continue;
            }

            $relative = substr($file->getPathname(), strlen($directory) + 1);
            $scanned++;
            $found = [...$found, ...$this->violations((string) file_get_contents($file->getPathname()), $relative)];
        }

        $I->assertGreaterThan(20, $scanned, 'The scan did not find the application.');
        $I->assertSame([], $found, "No static methods, no traits:\n" . implode("\n", $found));
    }

    public function theScanFindsWhatItLooksFor(UnitTester $I) : void
    {
        $source = <<<'PHP'
            <?php declare(strict_types=1);

            use Nette\StaticClass;

            trait Helper
            {
            }

            final class Foo
            {
                use StaticClass;
                use \Nette\SmartObject, Other;

                public static function create() : self
                {
                    return new self();
                }

                public function map(array $items) : array
                {
                    $suffix = 'x';

                    return array_map(static fn (string $item) : string => $item . $suffix, array_filter($items, function ($item) use ($suffix) {
                        return $item !== $suffix;
                    }));
                }
            }
            PHP;

        $I->assertSame(
            [
                'Foo.php:5 a trait',
                'Foo.php:11 a trait used in a class',
                'Foo.php:12 a trait used in a class',
                'Foo.php:14 a static method',
            ],
            $this->violations($source, 'Foo.php')
        );
    }

    /**
     * @return list<string>
     */
    private function violations(string $content, string $relative) : array
    {
        $found = [];

        foreach (self::FORBIDDEN as $pattern => $what) {
            if (preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE) > 0) {
                foreach ($matches[0] as $match) {
                    $line = substr_count(substr($content, 0, $match[1]), "\n") + 1;
                    $found[$line . $what] = $relative . ':' . $line . ' ' . $what;
                }
            }
        }

        ksort($found, SORT_NATURAL);

        return array_values($found);
    }
}
