<?php declare(strict_types=1);

namespace Tests\Unit;

use Tests\Support\UnitTester;

/**
 * The app runs on Bootstrap 5. Bootstrap 4 classes/attributes silently do nothing there (or, for bootstrap-select,
 * break the widget), so a leftover in a template, a grid or a legacy script is caught here.
 */
class Bootstrap5MarkupCest
{
    private const BANNED = [
        '/\bbtn-block\b/' => 'btn-block -> w-100',
        '/\bbtn-default\b/' => 'btn-default -> btn-secondary',
        '/\bbtn-xs\b/' => 'btn-xs -> btn-sm',
        '/\bbadge-(primary|secondary|success|danger|warning|info|light|dark)\b/' => 'badge-* -> bg-*',
        '/\bfont-weight-(bold|normal|light|bolder|italic)\b/' => 'font-weight-* -> fw-*',
        '/\b(ml|mr|pl|pr)-(auto|[0-5])\b/' => 'ml-/mr-/pl-/pr- -> ms-/me-/ps-/pe-',
        '/\btext-(left|right)\b/' => 'text-left/right -> text-start/end',
        '/\bfloat-(left|right)\b/' => 'float-left/right -> float-start/end',
        '/\bborder-(left|right)\b/' => 'border-left/right -> border-start/end',
        '/\bthead-(light|dark)\b/' => 'thead-light/dark -> table-light/dark',
        '/\bfont-italic\b/' => 'font-italic -> fst-italic',
        '/\bform-group\b/' => 'form-group -> mb-3',
        '/\bform-row\b/' => 'form-row -> row g-*',
        '/\bcustom-(select|control|file|switch|range|checkbox|radio)\b/' => 'custom-* -> form-*',
        '/\binput-group-(append|prepend)\b/' => 'input-group-append/prepend removed',
        '/\bsr-only\b/' => 'sr-only -> visually-hidden',
        '/\bdropdown-menu-(left|right)\b/' => 'dropdown-menu-left/right -> -start/-end',
        '/\bno-gutters\b/' => 'no-gutters -> g-0',
        '/\bdata-(toggle|target|dismiss|placement|content|parent|ride|slide|spy)=/' => 'data-* -> data-bs-*',
    ];

    public function noBootstrap4LeftoversInTemplatesAndScripts(UnitTester $I) : void
    {
        $root = dirname(__DIR__, 2);
        $found = [];
        $scanned = 0;

        foreach (['app', 'assets'] as $dir) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root . '/' . $dir, \FilesystemIterator::SKIP_DOTS));

            foreach ($iterator as $file) {
                if (!$file instanceof \SplFileInfo || !in_array($file->getExtension(), ['latte', 'php', 'js'], true)) {
                    continue;
                }

                $scanned++;
                $content = (string) file_get_contents($file->getPathname());

                foreach (self::BANNED as $pattern => $hint) {
                    if ($hint === '') {
                        continue;
                    }

                    if (preg_match($pattern, $content, $match, PREG_OFFSET_CAPTURE) === 1) {
                        $line = substr_count(substr($content, 0, $match[0][1]), "\n") + 1;
                        $found[] = substr($file->getPathname(), strlen($root) + 1) . ':' . $line . ' ' . $match[0][0] . ' (' . $hint . ')';
                    }
                }
            }
        }

        $I->assertGreaterThan(50, $scanned, 'The scan did not find the sources.');
        $I->assertSame([], $found, "Bootstrap 4 leftovers:\n" . implode("\n", $found));
    }

    /**
     * Ticket "do not use float-left/right in a card header, use flex": a floated block inside a Bootstrap 5 .card-header is not part of
     * the header's height, so the buttons stick out beside the content. A header is a flex row (`d-flex align-items-center`, the right
     * part `ms-auto`), and buttons of a list page belong into the header of the card that holds the grid, not into a float above it.
     */
    public function cardHeadersDoNotHoldFloats(UnitTester $I) : void
    {
        $root = dirname(__DIR__, 2) . '/app';
        $found = [];

        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)) as $file) {
            if (!$file instanceof \SplFileInfo || $file->getExtension() !== 'latte') {
                continue;
            }

            $content = (string) file_get_contents($file->getPathname());

            if (preg_match('~class="card-header[^"]*">\s*<(?:div|span)[^>]*class="[^"]*\bfloat-(?:start|end|left|right)\b~', $content, $match, PREG_OFFSET_CAPTURE) === 1) {
                $found[] = substr($file->getPathname(), strlen($root) + 1) . ':' . (substr_count(substr($content, 0, $match[0][1]), "\n") + 1);
            }

            if (preg_match('~<h1>[^<]*</h1>\s*<div class="float-(?:start|end|left|right)~', $content) === 1) {
                $found[] = substr($file->getPathname(), strlen($root) + 1) . ' (buttons floated beside the grid instead of the card header)';
            }
        }

        $I->assertSame([], $found, "Floats in a card header:\n" . implode("\n", $found));
    }
}
