<?php declare(strict_types=1);

namespace App\UI\Grid;

use Doctrine\ORM\QueryBuilder;

/**
 * The condition of an ID column filter: `->setFilterText()->setCondition(new IdFilterCondition('_user.id'))`. The filter is a
 * text input, the column an integer: the datagrid's own text filter ran `LOWER(_user.id) LIKE '%abc%'` on it (PostgreSQL:
 * no lower(bigint), a 500 swallowed by the grid). Now anything but a whole number matches nothing and a number is compared exactly.
 */
final class IdFilterCondition
{
    /** a BIGINT holds at most 19 digits, 18 can never overflow */
    private const PATTERN = '/^[0-9]{1,18}$/';

    public function __construct(
        private readonly string $field,
    )
    {
    }

    public function __invoke(QueryBuilder $queryBuilder, mixed $value) : void
    {
        $value = is_scalar($value) ? trim((string) $value) : '';

        if (preg_match(self::PATTERN, $value) !== 1) {
            $queryBuilder->andWhere('1 = 0');

            return;
        }

        $parameter = 'idFilter' . str_replace('.', '_', ltrim($this->field, '_'));

        $queryBuilder->andWhere($this->field . ' = :' . $parameter)
            ->setParameter($parameter, (int) $value);
    }
}
