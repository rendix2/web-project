<?php declare(strict_types=1);

namespace Tests\Unit;

use Tests\Support\UnitTester;

/**
 * Page layout convention: the page's `<h1>` lives in the header of the (first, main) card, and EVERY card header starts with a
 * Font Awesome icon. A page with several cards has exactly one h1 (the main card); the other headers use h2/h5/span + an icon.
 */
class CardHeaderTitleIconCest
{
    /** Error pages are bare, they have no card around them. */
    private const NO_CARD_PAGES = ['UI/Error/'];

    public function everyCardHeaderHasAnIcon(UnitTester $I) : void
    {
        $missing = [];
        $headers = 0;

        foreach ($this->templates() as $path => $content) {
            foreach ($this->scan($content)['headers'] as $header) {
                $headers++;

                if (!$this->iconPrecedesTitle($header['html'])) {
                    $missing[] = $path . ':' . $header['line'] . ' card-header title is not preceded by a Font Awesome icon';
                }
            }
        }

        $I->assertGreaterThan(15, $headers, 'The scan did not find the card headers.');
        $I->assertSame([], $missing, "Card headers without icon:\n" . implode("\n", $missing));
    }

    /**
     * Font Awesome 7 is bundled; a class that is not in the built stylesheet (typo, removed icon) renders as an empty box.
     * Every `fa-xxx` class used in a template must exist in the built stylesheet.
     */
    public function everyIconClassExistsInTheBundledFontAwesome(UnitTester $I) : void
    {
        $css = dirname(__DIR__, 2) . '/www/assets/app.css';

        if (!is_file($css)) {
            $I->markTestSkipped('www/assets/app.css is not built (npm run build).');
        }

        preg_match_all('/\.(fa-[a-z0-9-]+)(?=[,{:])/', (string) file_get_contents($css), $defined);
        $known = array_flip($defined[1]);
        // sizing/animation modifiers are not icons
        $modifiers = ['fa-fw', 'fa-lg', 'fa-xs', 'fa-sm', 'fa-2x', 'fa-3x', 'fa-4x', 'fa-5x', 'fa-spin', 'fa-pulse', 'fa-ul', 'fa-li', 'fa-border', 'fa-inverse', 'fa-stack', 'fa-stack-1x', 'fa-stack-2x'];
        $unknown = [];

        foreach ($this->templates() as $path => $content) {
            preg_match_all('/<i\s[^>]*class="([^"]*)"/', $content, $classes, PREG_OFFSET_CAPTURE);

            foreach ($classes[1] as [$classList, $offset]) {
                foreach (preg_split('/\s+/', $classList) ?: [] as $class) {
                    if (str_starts_with($class, 'fa-') && !isset($known[$class]) && !in_array($class, $modifiers, true)) {
                        $unknown[] = $path . ':' . (substr_count(substr($content, 0, $offset), "\n") + 1) . ' ' . $class;
                    }
                }
            }
        }

        $I->assertGreaterThan(200, count($known), 'The stylesheet does not define the icons.');
        $I->assertSame([], $unknown, "Unknown Font Awesome 5 icons:\n" . implode("\n", $unknown));
    }

    public function pageTitleIsTheH1InTheMainCardHeader(UnitTester $I) : void
    {
        $wrong = [];
        $h1s = 0;

        foreach ($this->templates() as $path => $content) {
            foreach (self::NO_CARD_PAGES as $prefix) {
                if (str_starts_with($path, $prefix)) {
                    continue 2;
                }
            }

            $scan = $this->scan($content);
            $h1s += count($scan['h1']);

            if ($scan['headers'] !== [] && $scan['h1'] === [] && !str_ends_with($path, '@layout.latte')) {
                $wrong[] = $path . ' has cards but no <h1> in the main card header';
            }

            if (count($scan['h1']) > 1) {
                $wrong[] = $path . ' has ' . count($scan['h1']) . ' <h1> (one per page, other cards use h2/h5)';
            }

            foreach ($scan['h1'] as $h1) {
                if ($h1['header'] === null) {
                    $wrong[] = $path . ':' . $h1['line'] . ' <h1> is outside of a card header';
                } elseif ($h1['header'] !== 0) {
                    $wrong[] = $path . ':' . $h1['line'] . ' <h1> is not in the FIRST (main) card header';
                }
            }
        }

        $I->assertGreaterThan(8, $h1s, 'The scan did not find the page titles.');
        $I->assertSame([], $wrong, "Misplaced page titles:\n" . implode("\n", $wrong));
    }

    /**
     * The icon belongs to the title, so it must come before the first printed value (`{_key}`, `{$var}`, `{include title}`);
     * an icon of an action button later in the header does not count.
     */
    private function iconPrecedesTitle(string $html) : bool
    {
        $icon = preg_match('/<i\s[^>]*class="[^"]*\bfa[srlb]?\b/', $html, $iconMatch, PREG_OFFSET_CAPTURE);

        if ($icon !== 1) {
            return false;
        }

        // tags are blanked out so a `{_key}` inside an attribute (title="...") is not taken for the title
        $text = (string) preg_replace_callback('/<[^>]*>/', static fn (array $m): string => str_repeat(' ', strlen($m[0])), $html);

        if (preg_match('/\{(_|\$|include\s)/', $text, $titleMatch, PREG_OFFSET_CAPTURE) !== 1) {
            return true;
        }

        return $iconMatch[0][1] < $titleMatch[0][1];
    }

    /**
     * @return array<string, string> path relative to `app/` => template source without Latte comments
     */
    private function templates() : array
    {
        $root = dirname(__DIR__, 2) . '/app';
        $templates = [];

        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)) as $file) {
            if (!$file instanceof \SplFileInfo || $file->getExtension() !== 'latte') {
                continue;
            }

            $content = (string) file_get_contents($file->getPathname());
            // comments may contain HTML; blank them out but keep the line numbers
            $content = (string) preg_replace_callback('/\{\*.*?\*\}/s', static fn (array $m): string => str_repeat("\n", substr_count($m[0], "\n")), $content);
            $templates[substr($file->getPathname(), strlen($root) + 1)] = $content;
        }

        ksort($templates);

        return $templates;
    }

    /**
     * Walks the `<div>` nesting: a header is the whole `.card-header` element, an h1 knows which header (by order) it sits in.
     *
     * @return array{headers: list<array{line: int, html: string}>, h1: list<array{line: int, header: int|null}>}
     */
    private function scan(string $content) : array
    {
        preg_match_all('/<div\b[^>]*>|<\/div>|<h1\b/', $content, $matches, PREG_OFFSET_CAPTURE);

        $stack = [];
        $headers = [];
        $h1 = [];

        foreach ($matches[0] as [$tag, $offset]) {
            $line = substr_count(substr($content, 0, $offset), "\n") + 1;

            if ($tag === '</div>') {
                $entry = array_pop($stack);

                if ($entry !== null && $entry['header'] !== null) {
                    $headers[$entry['header']]['html'] = substr($content, $entry['offset'], $offset - $entry['offset']);
                }

                continue;
            }

            if ($tag === '<h1') {
                $inside = null;

                foreach ($stack as $entry) {
                    if ($entry['header'] !== null) {
                        $inside = $entry['header'];
                    }
                }

                $h1[] = ['line' => $line, 'header' => $inside];

                continue;
            }

            $isHeader = preg_match('/\bclass="[^"]*\bcard-header\b/', $tag) === 1;
            $index = null;

            if ($isHeader) {
                $index = count($headers);
                $headers[$index] = ['line' => $line, 'html' => ''];
            }

            $stack[] = ['header' => $index, 'offset' => $offset];
        }

        return ['headers' => $headers, 'h1' => $h1];
    }
}
