<?php declare(strict_types=1);

namespace Tests\Unit;

use Tests\Support\UnitTester;

/**
 * A path in an Acceptance test is written with `Codeception\Util\Locator` (`Locator::find('button', ['name' => 'login'])`,
 * `Locator::firstElement('table > tbody > tr')`, `Locator::combine(...)`), not as a raw XPath string (`'//button[@name="login"]'`):
 * the Locator is readable, escapes the values and is one way to write a path everywhere. (The DomCrawler adapters of the Functional
 * tests, `tests/Support/Grid`, parse HTML and are not an Acceptance path.)
 */
class AcceptancePathsUseLocatorCest
{
    public function noAcceptanceTestWritesARawXPath(UnitTester $I) : void
    {
        $root = dirname(__DIR__);
        $violations = [];
        $scanned = 0;

        foreach (['Acceptance', 'Support/Step'] as $directory) {
            if (!is_dir($root . '/' . $directory)) {
                continue;
            }

            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root . '/' . $directory, \FilesystemIterator::SKIP_DOTS));

            foreach ($files as $file) {
                if (!$file instanceof \SplFileInfo || $file->getExtension() !== 'php') {
                    continue;
                }

                $scanned++;
                $content = (string) file_get_contents($file->getPathname());

                // a string literal that starts with `//`, `(//`, `./` or `.//` is an XPath
                if (preg_match_all('~[\'"]\(?\.?//~', $content, $matches, PREG_OFFSET_CAPTURE) > 0) {
                    foreach ($matches[0] as [, $offset]) {
                        $violations[] = substr($file->getPathname(), strlen($root) + 1) . ':' . (substr_count(substr($content, 0, $offset), "\n") + 1);
                    }
                }
            }
        }

        $I->assertGreaterThan(0, $scanned, 'The scan did not find the Acceptance tests.');
        $I->assertSame([], $violations, "Write the path with Codeception\\Util\\Locator, not as an XPath string:\n" . implode("\n", $violations));
    }
}
