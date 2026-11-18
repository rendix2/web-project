<?php declare(strict_types=1);

namespace Tests\Unit;

use Tests\Support\UnitTester;

/**
 * Queries go through the QueryBuilder, never hand-written DQL/SQL (AGENTS.md; the same guard in alfred, web-project and
 * family-tree): no `$em->createQuery('...')`, no `$connection->executeQuery($sql)` / `executeStatement($sql)` / `fetch*($sql)`.
 * Use `$em->createQueryBuilder()`, `$repository->createQueryBuilder('_alias')` or the DBAL `$connection->createQueryBuilder()`.
 *
 * Allowed: the calls WITHOUT an SQL string - `$queryBuilder->executeStatement()` / `executeQuery()` of a DBAL QueryBuilder and
 * `$result->fetchOne()` of its result - and `createQuery($queryBuilder->getDQL())` (the DQL comes from a QueryBuilder). Exempt: the
 * migrations (Schema API and the data steps `addSql()` is for).
 */
class QueriesUseTheQueryBuilderCest
{
    /**
     * @var array<string, string> pattern => what it is
     */
    private const array FORBIDDEN = [
        '~->createQuery\s*\((?!\s*\$\w+->getDQL\(\)\s*\))\s*[^)\s]~' => 'hand-written DQL (createQuery)',
        '~->createNativeQuery\s*\(~' => 'hand-written SQL (createNativeQuery)',
        '~->(?:executeQuery|executeStatement|executeUpdate)\s*\(\s*[^)\s]~' => 'hand-written SQL (execute* with an SQL string)',
        '~->fetch(?:One|Associative|AllAssociative|AllNumeric|FirstColumn|AllKeyValue|AllAssociativeIndexed)\s*\(\s*[^)\s]~' => 'hand-written SQL (fetch* with an SQL string)',
    ];

    public function theApplicationWritesNoQueriesByHand(UnitTester $I) : void
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

            if (str_starts_with($relative, 'Database/Migrations/')) {
                continue;
            }

            $scanned++;
            $content = (string) file_get_contents($file->getPathname());

            foreach (self::FORBIDDEN as $pattern => $what) {
                if (preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE) > 0) {
                    foreach ($matches[0] as $match) {
                        $line = substr_count(substr($content, 0, $match[1]), "\n") + 1;
                        $found[] = $relative . ':' . $line . ' ' . $what;
                    }
                }
            }
        }

        $I->assertGreaterThan(20, $scanned, 'The scan did not find the application.');
        $I->assertSame([], $found, "Use the QueryBuilder:\n" . implode("\n", $found));
    }
}
