<?php declare(strict_types=1);

namespace Tests\Unit;

use Tests\Support\UnitTester;

/**
 * `->toggle('some-id')` of a form control shows / hides every element with that HTML id (netteForms.js:
 * `document.querySelectorAll('#some-id')`), and the id of an element comes from `->setOption('id', 'some-id')`. So:
 *  - a toggled id is defined in the same file (otherwise the toggle hits nothing - or a foreign form's element),
 *  - an id option is toggled in the same file (otherwise it is a dead option),
 *  - an id is defined ONCE in the whole application (two forms with the same id toggle each other: the date-to of the person page).
 * The rendered pages are checked by family-tree's Functional/ToggleTargetsAreUniqueCest; none of these forms toggles anything yet, so this is the guard for the first one.
 */
class FormToggleIdsCest
{
    public function toggledIdsAreDefinedOnceInTheSameFile(UnitTester $I) : void
    {
        $root = dirname(__DIR__, 2) . '/app';
        $violations = [];
        $definedIn = [];

        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));

        foreach ($files as $file) {
            if (!$file instanceof \SplFileInfo || $file->getExtension() !== 'php') {
                continue;
            }

            $name = substr($file->getPathname(), strlen($root) + 1);
            $source = (string) file_get_contents($file->getPathname());

            preg_match_all('~->toggle\(\s*\'([\w.:-]+)\'~', $source, $toggled);
            preg_match_all('~->setOption\(\s*\'id\'\s*,\s*\'([\w.:-]+)\'\s*\)~', $source, $defined);

            foreach (array_unique($toggled[1]) as $id) {
                if (!in_array($id, $defined[1], true)) {
                    $violations[] = $name . ': toggles "' . $id . '" but does not define an element with that id';
                }
            }

            foreach (array_count_values($defined[1]) as $id => $count) {
                if ($count > 1) {
                    $violations[] = $name . ': defines the id "' . $id . '" ' . $count . 'x';
                }

                if (!in_array((string) $id, $toggled[1], true)) {
                    $violations[] = $name . ': defines the id "' . $id . '" which nothing toggles (a dead option, a duplicate id on the page)';
                }

                $definedIn[(string) $id][] = $name;
            }
        }

        foreach ($definedIn as $id => $names) {
            if (count(array_unique($names)) > 1) {
                $violations[] = 'the id "' . $id . '" is defined in more files: ' . implode(', ', array_unique($names));
            }
        }

        $I->assertSame([], $violations, "Toggle ids:\n" . implode("\n", $violations));
    }
}
