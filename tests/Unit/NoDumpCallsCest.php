<?php declare(strict_types=1);

namespace Tests\Unit;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\Support\UnitTester;

/**
 * No debugging output in the application: `bdump()` of a caught exception shows it only in the Tracy bar of a developer, in
 * production the error disappears without a trace. An exception is logged through the injected Tracy\ILogger.
 */
class NoDumpCallsCest
{
    public function theApplicationDumpsNothing(UnitTester $I) : void
    {
        $directory = dirname(__DIR__, 2) . '/app';
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS));
        $violations = [];

        foreach ($files as $file) {
            assert($file instanceof SplFileInfo);

            $pattern = match ($file->getExtension()) {
                'php' => '~(?<![\w>$])(?:bdump|dumpe|dump|var_dump|print_r)\s*\(|Debugger::(?:barDump|dump)\s*\(~i',
                'latte' => '~\{(?:dump|bdump|debugbreak)\b|\|dump\b~',
                default => null,
            };

            if ($pattern === null) {
                continue;
            }

            $lines = file($file->getPathname());

            foreach ($lines === false ? [] : $lines as $number => $line) {
                if (preg_match($pattern, $line) === 1) {
                    $violations[] = substr($file->getPathname(), strlen($directory) + 1) . ':' . ($number + 1);
                }
            }
        }

        $I->assertSame([], $violations, 'Log through Tracy\ILogger instead of dumping.');
    }
}
