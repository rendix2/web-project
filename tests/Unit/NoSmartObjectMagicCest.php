<?php declare(strict_types=1);

namespace Tests\Unit;

use Tests\Support\UnitTester;

/**
 * The magic properties of Nette components (`$grid->presenter`, `$this->template`) go through SmartObject: nette/utils 4.1 reports
 * them as a deprecation, and `tracy.strictMode` turns a deprecation into a 500 - a request in a browser (the Acceptance suite) fails
 * while the Functional suite (strictMode off) stays green. The real getters are used instead: `getPresenter()`, `getTemplate()`.
 */
class NoSmartObjectMagicCest
{
    private const PATTERN = '~->(?:presenter|template)\b(?!\s*\()~';

    public function theApplicationUsesTheGettersOfTheComponents(UnitTester $I) : void
    {
        $root = dirname(__DIR__, 2);
        $found = [];
        $scanned = 0;

        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root . '/app', \FilesystemIterator::SKIP_DOTS));

        foreach ($files as $file) {
            if (!$file instanceof \SplFileInfo || $file->getExtension() !== 'php') {
                continue;
            }

            $scanned++;
            $content = (string) file_get_contents($file->getPathname());

            if (preg_match_all(self::PATTERN, $content, $matches, PREG_OFFSET_CAPTURE) > 0) {
                foreach ($matches[0] as [$match, $offset]) {
                    $found[] = substr($file->getPathname(), strlen($root) + 1) . ':' . (substr_count(substr($content, 0, $offset), "\n") + 1) . ' ' . $match;
                }
            }
        }

        $I->assertGreaterThan(50, $scanned, 'The scan did not find the sources.');
        $I->assertSame([], $found, "Use getPresenter() / getTemplate() instead of:\n" . implode("\n", $found));
    }
}
