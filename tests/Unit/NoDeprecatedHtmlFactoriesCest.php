<?php declare(strict_types=1);

namespace Tests\Unit;

use Tests\Support\UnitTester;

/**
 * `Html::fromText()` / `Html::fromHtml()` are deprecated in nette/utils 4.1 ("use Html::text()", "use Html::html()"): the plain text
 * and the raw HTML of an element come from the static factories `Html::text($text)` / `Html::html($html)`. (Under tracy.strictMode a
 * deprecation is a 500.)
 */
class NoDeprecatedHtmlFactoriesCest
{
    private const PATTERN = '~\bHtml::from(?:Text|Html)\s*\(~';

    public function theApplicationUsesTheStaticFactoriesText(UnitTester $I) : void
    {
        $root = dirname(__DIR__, 2);
        $found = [];
        $scanned = 0;

        foreach (['app', 'tests'] as $directory) {
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root . '/' . $directory, \FilesystemIterator::SKIP_DOTS));

            foreach ($files as $file) {
                if (!$file instanceof \SplFileInfo || !in_array($file->getExtension(), ['php', 'latte'], true)) {
                    continue;
                }

                // this very test names the deprecated calls
                if ($file->getFilename() === basename(__FILE__) || str_contains($file->getPathname(), '/_output/') || str_contains($file->getPathname(), '/_generated/')) {
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
        }

        $I->assertGreaterThan(50, $scanned, 'The scan did not find the sources.');
        $I->assertSame([], $found, "Use Html::text() / Html::html() instead of:\n" . implode("\n", $found));
    }
}
