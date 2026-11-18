<?php declare(strict_types=1);

namespace App\UI\Grid;

use Contributte\Datagrid\Column\ActionCallback;
use Contributte\Datagrid\Datagrid;
use Contributte\Datagrid\DataSource\DoctrineDataSource;
use Contributte\Datagrid\InlineEdit\InlineEdit;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Nette\Http\IResponse;
use Ramsey\Uuid\Doctrine\UuidType;
use Ramsey\Uuid\Uuid;

/**
 * A datagrid whose row conditions (`allowRowsAction()`, `allowRowsInlineEdit()`) are enforced on the server. Every grid of the
 * project is one (`new SecuredDatagrid()`, never the plain Datagrid: tests/Unit/GridsAreSecuredCest).
 *
 * The datagrid asks those conditions only when it DRAWS a row (to hide the link or the button). The signals that do the work -
 * `handleActionCallback()`, `handleInlineEdit()` and the submit of the inline edit - never ask them again, so anybody could send
 * the request by hand (an admin deleting himself, which the grid hides). Here the very same condition is asked for the row of the
 * request first; a row the grid shows but the condition does not allow is a 403 and no handler runs.
 *
 * The id of the request is checked against the type of the primary key first: a malformed one (`abc`, `1.5`, empty, beyond
 * the integer range, an invalid uuid) can be no row, it is a 404 - it never reaches Doctrine / PostgreSQL
 * (`invalid input syntax for type bigint`, ValueNotConvertible), which answered 500.
 *
 * Then the row has to be one of the grid's OWN rows: the query of the data source may restrict the rows, but a signal names any
 * id of the table. An id the grid would not show is a 404 - the same answer as for an id of no row, so a foreign row cannot be
 * told from a missing one (a 403 would say "it exists").
 *
 * Taken over from alfred (Chatbot\App\WebModule\Grids\SecuredDatagrid); web-project is the template of alfred and family-tree.
 */
class SecuredDatagrid extends Datagrid
{
    /**
     * Ranges of the integer primary keys (DBAL type name => [min, max]).
     */
    private const INTEGER_RANGES = [
        Types::SMALLINT => [-32768, 32767],
        Types::INTEGER => [-2147483648, 2147483647],
        Types::BIGINT => [PHP_INT_MIN, PHP_INT_MAX],
    ];

    public function addInlineEdit(?string $primaryWhereColumn = null) : InlineEdit
    {
        $inlineEdit = parent::addInlineEdit($primaryWhereColumn);

        // the first handler of the submit: it runs before the ones the grids add
        $inlineEdit->onSubmit[] = function (mixed $id) : void {
            $this->assertRowAllowed('inline_edit', null, $id);
        };

        return $inlineEdit;
    }

    public function handleInlineEdit(mixed $id) : void
    {
        // a grid without the inline edit has no such signal
        if ($this->inlineEdit === null) {
            $this->getPresenter()->error('Not Found', IResponse::S404_NotFound);
        }

        $this->assertRowAllowed('inline_edit', null, $id);

        parent::handleInlineEdit($id);
    }

    public function handleActionCallback(mixed $__key, mixed $__id) : void
    {
        // an unknown action, or one that is no callback (a link like "edit"), made the datagrid throw DatagridException (a 500)
        if (!is_string($__key) || !isset($this->actions[$__key]) || !$this->actions[$__key] instanceof ActionCallback) {
            $this->getPresenter()->error('Not Found', IResponse::S404_NotFound);
        }

        $this->assertRowAllowed('action', $__key, $__id);

        parent::handleActionCallback($__key, $__id);
    }

    /**
     * The datagrid passes the column of the submitted inline edit straight from the request
     * (inline_edit[_primary_where_column]) and it ends up in DQL: a forged one was a 500. Only the configured column is used.
     */
    public function redrawItem(string|int $id, mixed $primaryWhereColumn = null) : void
    {
        parent::redrawItem($id, $this->inlineEdit?->getPrimaryWhereColumn());
    }

    private function assertRowAllowed(string $name, ?string $key, mixed $id) : void
    {
        // a malformed id is a row that cannot be found
        if (!is_scalar($id) || !$this->isValidRowId((string) $id)) {
            $this->getPresenter()->error('Not Found', IResponse::S404_NotFound);
        }

        // missing or foreign: for this user the row does not exist (one answer for both, nothing is revealed)
        if (!$this->isRowInDataSource((string) $id)) {
            $this->getPresenter()->error('Not Found', IResponse::S404_NotFound);
        }

        $condition = $this->getRowCondition($name, $key);

        if (!is_callable($condition)) {
            return; // no condition, everybody who reaches the page may do it
        }

        $row = $this->findRow((string) $id);

        if ($row === null) {
            $this->getPresenter()->error('Not Found', IResponse::S404_NotFound);
        }

        // the row is one of the grid's (the user sees it), only the action is not allowed
        if (($condition)($row) !== true) {
            $this->getPresenter()->error('Forbidden', IResponse::S403_Forbidden);
        }
    }

    /**
     * Whether the id of the request fits the type of the primary key of the grid's entity (only a Doctrine data source is checked).
     */
    private function isValidRowId(string $id) : bool
    {
        $entity = $this->findEntity();

        if ($entity === null) {
            return true;
        }

        [$entityManager, $entityClass] = $entity;
        $type = $entityManager->getClassMetadata($entityClass)->getTypeOfField($this->getPrimaryKey());

        if ($type === UuidType::NAME) {
            return Uuid::isValid($id);
        }

        if ($type === null || !isset(self::INTEGER_RANGES[$type])) {
            return true; // a string key etc.: any value is a valid query parameter
        }

        [$min, $max] = self::INTEGER_RANGES[$type];

        // digits only (an optional minus, no leading zeros, no "+", no spaces) and within the range: "abc", "", "1.5" and a number
        // beyond PHP_INT_MAX (cast to PHP_INT_MAX) do not survive the round trip
        return (string) (int) $id === $id && (int) $id >= $min && (int) $id <= $max;
    }

    /**
     * Whether the grid's own query (with all its restrictions) has the row: the datagrid's own COUNT over a copy of its data source
     * narrowed to the row (nothing is hydrated, no DQL is written here). Only a Doctrine data source is checked.
     */
    private function isRowInDataSource(string $id) : bool
    {
        $source = $this->getDataSource();

        if (!$source instanceof DoctrineDataSource) {
            return true;
        }

        $copy = new DoctrineDataSource(clone $source->getDataSource(), $this->getPrimaryKey());
        $copy->filterOne([$this->getPrimaryKey() => $id]);

        return $copy->getCount() > 0;
    }

    private function findRow(string $id) : ?object
    {
        $entity = $this->findEntity();

        if ($entity === null) {
            return null;
        }

        [$entityManager, $entityClass] = $entity;

        // NOT the grid's own query: it may join and filter associations, and the entities it hydrates would stay in the identity
        // map with partial collections for the handler that runs next. The plain entity is what the handlers load themselves.
        return $entityManager->getRepository($entityClass)->findOneBy([$this->getPrimaryKey() => $id]);
    }

    /**
     * The entity manager and the root entity of the grid's Doctrine data source.
     *
     * @return array{0: EntityManagerInterface, 1: class-string}|null
     */
    private function findEntity() : ?array
    {
        $source = $this->getDataSource();

        if (!$source instanceof DoctrineDataSource) {
            return null;
        }

        $queryBuilder = $source->getDataSource();
        $entityClass = $queryBuilder->getRootEntities()[0] ?? null;

        if ($entityClass === null) {
            return null;
        }

        return [$queryBuilder->getEntityManager(), $entityClass];
    }

}
