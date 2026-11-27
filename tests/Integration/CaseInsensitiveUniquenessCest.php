<?php declare(strict_types=1);

namespace Tests\Integration;

use App\Model\Entity\UserEntity;
use Codeception\Attribute\DataProvider;
use Codeception\Example;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Tests\Support\IntegrationTester;

/**
 * The database itself refuses a username / e-mail that differs only in case (unique indexes on lower()): the check of the
 * application alone lets two parallel registrations of `Petr` and `petr` pass.
 */
class CaseInsensitiveUniquenessCest
{
    /**
     * @param Example<int, string> $example
     */
    #[DataProvider('columns')]
    public function theDatabaseRefusesTheSameValueInOtherCase(IntegrationTester $I, Example $example) : void
    {
        $column = $example[0];
        assert($column === 'username' || $column === 'email');
        $first = $I->createUser('incase');

        $second = new UserEntity();
        $second->name = 'In';
        $second->surname = 'Case';
        $second->username = $column === 'username' ? strtoupper($first->username) : $first->username . 'x';
        $second->email = $column === 'email' ? strtoupper($first->email) : 'x' . $first->email;
        $second->password = $first->password;
        $second->isActive = true;
        $I->em()->persist($second);

        $I->expectThrowable(UniqueConstraintViolationException::class, static function () use ($I) : void {
            $I->em()->flush();
        });
    }

    /**
     * @return list<list<string>>
     */
    protected function columns() : array
    {
        return [['username'], ['email']];
    }
}
