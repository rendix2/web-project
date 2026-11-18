<?php declare(strict_types=1);

namespace Tests\Unit;

use Tests\Support\UnitTester;

/**
 * The Unit suite runs without the DI container, the database and the network (tests/Unit.suite.yml enables only Asserts and
 * `make unit` does not prepare the database). This guard keeps a Unit test from reaching them on its own: a test that needs a
 * service, the database or HTTP belongs to the Integration suite. A recording fake (tests/Support/Unit) is fine.
 * (The same test lives in alfred.)
 */
class UnitTestsStayIsolatedCest
{
    private const array FORBIDDEN = [
        '~->grabService\s*\(~' => 'the DI container (grabService)',
        '~\bNette\\\\(?:DI\\\\Container|Bootstrap\\\\Configurator)\b~' => 'the DI container',
        '~\bDoctrine\\\\DBAL\\\\~' => 'the database (DBAL)',
        '~\bDoctrine\\\\ORM\\\\(?:EntityManager\b(?!Interface)|ORMSetup)~' => 'the database (a real EntityManager)',
        '~\bApp\\\\Database\\\\EntityManagerDecorator\b~' => 'the database (EntityManagerDecorator)',
        '~\bnew\s+\\\\?PDO\b|\bpg_connect\s*\(~' => 'the database (PDO / pg)',
        '~\bGuzzleHttp\\\\|\bcurl_init\s*\(|\bfsockopen\s*\(~' => 'the network',
        '~\bTests\\\\Support\\\\Helper\\\\~' => 'a helper of the database',
    ];

    public function unitTestsReachNoContainerDatabaseOrNetwork(UnitTester $I) : void
    {
        $directory = dirname(__DIR__) . '/Unit';
        $found = [];
        $scanned = 0;

        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS));

        foreach ($files as $file) {
            if (!$file instanceof \SplFileInfo || $file->getExtension() !== 'php' || $file->getPathname() === __FILE__) {
                continue;
            }

            $scanned++;
            $content = (string) file_get_contents($file->getPathname());

            foreach (self::FORBIDDEN as $pattern => $reaches) {
                if (preg_match($pattern, $content, $match, PREG_OFFSET_CAPTURE) === 1) {
                    $line = substr_count(substr($content, 0, $match[0][1]), "\n") + 1;
                    $found[] = substr($file->getPathname(), strlen($directory) + 1) . ':' . $line . ' reaches ' . $reaches;
                }
            }
        }

        $I->assertGreaterThan(10, $scanned, 'The scan did not find the Unit tests.');
        $I->assertSame([], $found, "Move these tests to tests/Integration:\n" . implode("\n", $found));
    }
}
