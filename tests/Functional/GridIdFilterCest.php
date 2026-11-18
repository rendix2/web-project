<?php declare(strict_types=1);

namespace Tests\Functional;

use App\Database\EntityManagerDecorator;
use App\Model\Entity\RoleEntity;
use Codeception\Attribute\DataProvider;
use Codeception\Attribute\Group;
use Codeception\Example;
use Tests\Support\FunctionalTester;
use Tests\Support\Grid\DatagridPage;

/**
 * The ID filters are text inputs over an integer column: the datagrid's own text filter ran `LOWER(_role.id) LIKE '%abc%'`
 * (PostgreSQL has no lower(bigint): an exception in the log, swallowed by the grid). With App\UI\Grid\IdFilterCondition a value
 * that is not a whole number matches nothing, a real id still finds its row.
 */
#[Group('grid')]
class GridIdFilterCest
{
    private int $exceptionLogSize;

    public function _before(FunctionalTester $I) : void
    {
        $this->exceptionLogSize = $this->logSize();

        $I->loginAs(FunctionalTester::ROLE_ADMIN);
    }

    public function _after(FunctionalTester $I) : void
    {
        $I->assertSame($this->exceptionLogSize, $this->logSize(), 'The request wrote to the exception log.');
    }

    /**
     * @param Example<int, string> $example
     */
    #[DataProvider('pagesProvider')]
    public function valueThatIsNotNumberMatchesNothing(FunctionalTester $I, Example $example) : void
    {
        $url = $example[0];
        assert(is_string($url));

        foreach (['abc', '1.5', '1 OR 1=1', "1' OR '1'='1", '-1', '99999999999999999999999'] as $junk) {
            $I->amOnPage($url . '?' . http_build_query(['grid-filter' => ['id' => $junk]]));
            $this->assertPageIsFine($I, $url . ' id = ' . $junk);

            $I->assertSame([], (new DatagridPage($I->grabPageSource()))->rowIds('grid'), $url . ': id = ' . $junk . ' shows rows');
        }
    }

    public function aRealIdStillFindsItsRow(FunctionalTester $I) : void
    {
        $em = $I->grabService(EntityManagerDecorator::class);

        $role = new RoleEntity();
        $role->name = 'GridIdFilter' . random_int(100000, 999999);
        $role->description = 'grid id filter test';
        $em->persist($role);
        $em->flush();
        $I->trackCreatedEntity(RoleEntity::class, $role->id);

        $I->amOnPage('/admin.role.list/?' . http_build_query(['grid-filter' => ['id' => (string) $role->id]]));
        $this->assertPageIsFine($I, 'role id = ' . $role->id);

        $I->assertSame([(string) $role->id], (new DatagridPage($I->grabPageSource()))->rowIds('grid'));
    }

    /**
     * @return array<string, array{0: string}>
     */
    protected function pagesProvider() : array
    {
        return [
            '/admin.user.list/' => ['/admin.user.list/'],
            '/admin.role.list/' => ['/admin.role.list/'],
        ];
    }

    private function assertPageIsFine(FunctionalTester $I, string $what) : void
    {
        $I->seeResponseCodeIs(200);
        $source = $I->grabPageSource();

        $I->assertStringNotContainsString('id="tracy-bs"', $source, $what);
        $I->assertStringNotContainsString('invalid input syntax', $source, $what);
    }

    private function logSize() : int
    {
        clearstatcache();
        $size = 0;

        foreach ([dirname(__DIR__, 2) . '/log/exception.log', dirname(__DIR__, 2) . '/log/web/exception.log'] as $file) {
            $size += is_file($file) ? (int) filesize($file) : 0;
        }

        return $size;
    }
}
