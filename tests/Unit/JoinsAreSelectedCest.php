<?php declare(strict_types=1);

namespace Tests\Unit;

use Tests\Support\UnitTester;

/**
 * A Doctrine `join()` / `innerJoin()` / `leftJoin()` without `addSelect()` only FILTERS: the joined entity is not hydrated, so it stays a
 * lazy proxy and every later read of it costs a query of its own (N+1 - the person detail made 218 queries, the statistics 1600).
 * Every join of a query that returns entities has to be selected: `->addSelect('_town')->leftJoin('_person.birthTown', '_town')`.
 *
 * Exempt: a query that selects scalars / aggregates (`select('_town.name')`, `COUNT(...)`) - nothing is hydrated; and a join that only
 * filters a COLLECTION (selecting it would hydrate just the filtered rows into the collection) marked with `@join-filter-only` in the
 * statement - prefer `:x MEMBER OF e.collection`, which needs no join.
 */
class JoinsAreSelectedCest
{
    public function theDetectorFindsAJoinThatIsNotSelected(UnitTester $I) : void
    {
        $I->assertCount(1, $this->violations("<?php \$q = \$r->createQueryBuilder('p')->leftJoin('p.town', 't')->getQuery();"));
        // 't' is selected, 'c' is not
        $I->assertSame([[1, 'p.country AS c']], $this->violations("<?php \$q = \$r->createQueryBuilder('p')->join('p.town', 't')->innerJoin('p.country', 'c')->addSelect('t')->getQuery();"));
    }

    public function theDetectorAcceptsSelectedAndScalarAndMarkedJoins(UnitTester $I) : void
    {
        $I->assertSame([], $this->violations("<?php \$q = \$r->createQueryBuilder('p')->addSelect('t')->leftJoin('p.town', 't')->getQuery();"));
        $I->assertSame([], $this->violations("<?php \$q = \$r->createQueryBuilder('p')->addSelect('t', 'c')->leftJoin('p.town', 't')->join('p.country', 'c')->getQuery();"));
        $I->assertSame([], $this->violations("<?php \$q = \$r->createQueryBuilder('p')->select('t.name')->addSelect('COUNT(p.id) AS n')->join('p.town', 't')->getQuery();"));
        $I->assertSame([], $this->violations("<?php \$q = \$r->createQueryBuilder('e')->join('e.chats', 'c') /* @join-filter-only */ ->where('c = :c')->getQuery();"));
    }

    public function everyJoinOfTheAppIsSelected(UnitTester $I) : void
    {
        $root = dirname(__DIR__, 2);
        $found = [];

        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root . '/app', \FilesystemIterator::SKIP_DOTS));

        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo || $file->getExtension() !== 'php' || str_contains($file->getPathname(), '/Database/Migrations/')) {
                continue;
            }

            foreach ($this->violations((string) file_get_contents($file->getPathname())) as [$line, $join]) {
                $found[] = substr($file->getPathname(), strlen($root) + 1) . ':' . $line . ' ' . $join;
            }
        }

        $I->assertSame([], $found, "A join without addSelect leaves lazy proxies (N+1):\n" . implode("\n", $found));
    }

    /**
     * @return list<array{0: int, 1: string}> [line, "relation AS alias"] of every join that is not selected
     */
    private function violations(string $source) : array
    {
        $found = [];
        $offset = 0;

        foreach (explode(';', $source) as $statement) {
            if (preg_match_all("~->(?:inner|left)?[Jj]oin\\(\\s*'([^']+)'\\s*,\\s*'([^']+)'~", $statement, $joins, PREG_SET_ORDER | PREG_OFFSET_CAPTURE) > 0
                && !str_contains($statement, '@join-filter-only')) {
                preg_match_all("~(?:addSelect|->select)\\(([^;]*?)\\)\\s*(?=->|$)~s", $statement, $selects);
                $arguments = implode(' ', $selects[1]);
                preg_match_all("~'([^']*)'~", $arguments, $quoted);
                $scalar = false;

                foreach ($quoted[1] as $argument) {
                    $scalar = $scalar || str_contains($argument, '.') || str_contains($argument, '(');
                }

                foreach ($joins as $join) {
                    $selected = preg_match("~'" . preg_quote($join[2][0], '~') . "'~", $arguments) === 1;

                    if (!$selected && !$scalar) {
                        $found[] = [substr_count($source, "\n", 0, $offset + (int) $join[0][1]) + 1, $join[1][0] . ' AS ' . $join[2][0]];
                    }
                }
            }

            $offset += strlen($statement) + 1;
        }

        return $found;
    }
}
