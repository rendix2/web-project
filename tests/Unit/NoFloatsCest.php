<?php declare(strict_types=1);

namespace Tests\Unit;

use Tests\Support\UnitTester;

/**
 * Ticket "do not use float-left/right in a card, use flex": the layout of a card (its header, body, footer) is a flex row
 * (`d-flex justify-content-between align-items-center`, the right part `ms-auto`, a gap `gap-2`), never a float - a floated block is
 * not part of the height of its parent, so it sticks out of the card and needs a clearfix.
 *
 * Nothing in the application sources floats: no Bootstrap `float-*` / `pull-*` / `clearfix` class, no inline `float:` and no
 * `float:` in the project's own CSS (the vendor CSS of a library is not scanned).
 */
class NoFloatsCest
{
    private const PATTERN = '~\bfloat-(?:start|end|left|right|none|sm-\w+|md-\w+|lg-\w+|xl-\w+|xxl-\w+)\b|\bpull-(?:left|right)\b|\bclearfix\b|\bfloat\s*:\s*(?:left|right|inline-start|inline-end)\b~';

    public function nothingInTheApplicationFloats(UnitTester $I) : void
    {
        $root = dirname(__DIR__, 2);
        $found = [];
        $scanned = 0;

        foreach (['app', 'assets'] as $directory) {
            if (!is_dir($root . '/' . $directory)) {
                continue;
            }

            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root . '/' . $directory, \FilesystemIterator::SKIP_DOTS));

            foreach ($files as $file) {
                if (!$file instanceof \SplFileInfo || !in_array($file->getExtension(), ['latte', 'php', 'css', 'scss', 'js'], true)) {
                    continue;
                }

                // third party scripts that were copied into the sources
                if (str_contains($file->getPathname(), '/legacy/')) {
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
        $I->assertSame([], $found, "Use flex (d-flex, justify-content-between, ms-auto, gap-*) instead of:\n" . implode("\n", $found));
    }
}
