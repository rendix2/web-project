<?php declare(strict_types=1);

namespace Tests\Unit;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use Tests\Support\UnitTester;

/**
 * The conventions of the migrations (AGENTS.md, "Migrations"), read from the source of app/Database/Migrations - nothing runs:
 *
 * - the structure changes through the Schema API, `addSql()` / `executeStatement()` only for data (INSERT / UPDATE / DELETE)
 *   and for what the Schema API cannot express (`CREATE / DROP EXTENSION`, an expression or partial index, an
 *   `ALTER ... TYPE ... USING` conversion), such a statement with a comment saying why;
 * - indexes and foreign keys have an explicit name `K__<Table>__<Columns>` (`K__User_password_request_attempt__Email_created_at`),
 *   `UK__...` (unique), `FK__...`;
 * - `down()` is not empty: it reverts the tables `up()` changed, or calls `throwIrreversibleMigrationException()`;
 * - one migration changes the structure of at most one table.
 *
 * Every migration is checked (ported from alfred 2026-11-18): web-project is not deployed anywhere, so a migration that breaks a
 * rule is fixed, not excused (AGENTS.md "Migrations use the Schema API").
 */
class MigrationConventionsCest
{
    /**
     * The first migration that has to follow the conventions: the first one of all.
     */
    private const string CUTOFF = 'Version20230621145918';

    // no EXCEPTIONS list (alfred has one for migrations that already ran in production): web-project is not deployed, a
    // migration that breaks a rule is fixed

    private const string RULE_SCHEMA_API = 'schema-api';
    private const string RULE_NAMING = 'naming';
    private const string RULE_DOWN = 'down';
    private const string RULE_ONE_TABLE = 'one-table';

    private const array NAMED_METHODS = [
        'addIndex' => 'K',
        'addUniqueIndex' => 'UK',
        'addForeignKeyConstraint' => 'FK',
    ];

    private const array NAMED_ARGUMENTS = [
        'addIndex' => [0, 'columnNames', 1, 'indexName'],
        'addUniqueIndex' => [0, 'columnNames', 1, 'indexName'],
        'addForeignKeyConstraint' => [1, 'localColumnNames', 4, 'name'],
    ];

    /**
     * Schema methods that change (or reach for a change) the structure of the table in their first argument.
     */
    private const array SCHEMA_TABLE_METHODS = ['createTable', 'getTable', 'dropTable', 'renameTable'];

    private const string NAME_PATTERN = '~^(?:K|UK|FK)__[A-Z][a-z0-9_]*?(?:__[A-Z][a-z0-9_]*?)+$~';

    /** @var array<string, list<string>> rule => violations of the migration checkMigration() is checking */
    private array $migrationViolations;

    public function migrationsChangeTheStructureThroughTheSchemaApi(UnitTester $I) : void
    {
        $this->assertRule($I, self::RULE_SCHEMA_API, 'Use the Schema API ($schema->createTable(), getTable()->addColumn(), ...) instead of raw DDL; '
            . 'addSql() is for data and for what DBAL cannot express, with a comment why');
    }

    public function indexesAndKeysHaveConventionalNames(UnitTester $I) : void
    {
        $this->assertRule($I, self::RULE_NAMING, 'Name indexes and keys explicitly: K__<Table>__<Columns> (columns joined by _), UK__... (unique), FK__... (foreign key), '
            . '<Table> / <Column> the real name with a capital first letter');
    }

    public function downRevertsUpOrThrows(UnitTester $I) : void
    {
        $this->assertRule($I, self::RULE_DOWN, 'down() has to revert what up() did, or call $this->throwIrreversibleMigrationException(\'why\')');
    }

    public function oneMigrationChangesOneTable(UnitTester $I) : void
    {
        $this->assertRule($I, self::RULE_ONE_TABLE, 'Split the migration: one migration changes the structure of one table (a literal name)');
    }

    private function assertRule(UnitTester $I, string $rule, string $hint) : void
    {
        $violations = $this->violations();
        $found = [];

        $I->assertArrayHasKey(self::CUTOFF, $violations, 'The CUTOFF migration does not exist.');

        foreach ($violations as $version => $rules) {
            foreach ($rules[$rule] as $message) {
                $found[] = $version . ': ' . $message;
            }
        }

        $I->assertSame([], $found, $hint . ":\n" . implode("\n", $found));
    }

    /**
     * @return array<string, array<string, list<string>>> migration (from CUTOFF on) => rule => violations
     */
    private function violations() : array
    {
        $directory = dirname(__DIR__, 2) . '/app/Database/Migrations';
        $files = glob($directory . '/Version*.php');
        $parser = (new ParserFactory())->createForHostVersion();
        $result = [];

        foreach ($files === false ? [] : $files as $file) {
            $version = basename($file, '.php');

            if ($version < self::CUTOFF) {
                continue;
            }

            $statements = $parser->parse((string) file_get_contents($file)) ?? [];
            $result[$version] = $this->checkMigration($statements);
        }

        return $result;
    }

    /**
     * @param array<Node\Stmt> $statements
     * @return array<string, list<string>>
     */
    private function checkMigration(array $statements) : array
    {
        $finder = new NodeFinder();
        $methods = [];

        foreach ($finder->findInstanceOf($statements, ClassMethod::class) as $method) {
            $methods[$method->name->toString()] = $method;
        }

        $this->migrationViolations = [self::RULE_SCHEMA_API => [], self::RULE_NAMING => [], self::RULE_DOWN => [], self::RULE_ONE_TABLE => []];
        $tables = ['up' => [], 'down' => []];

        foreach (['up', 'down'] as $name) {
            if (!isset($methods[$name])) {
                $this->migrationViolations[self::RULE_DOWN][] = $name . '() is missing';

                continue;
            }

            $tables[$name] = $this->checkMethod($methods[$name]);
        }

        if (isset($methods['down'])) {
            $this->checkDown($methods['down'], $tables['up'], $tables['down']);
        }

        $changed = array_values(array_unique([...$tables['up'], ...$tables['down']]));

        if (count($changed) > 1 || in_array('?', $changed, true)) {
            $this->migrationViolations[self::RULE_ONE_TABLE][] = 'changes the tables ' . implode(', ', $changed) . ' (? = a name that is not a literal)';
        }

        return $this->migrationViolations;
    }

    /**
     * @return list<string> the tables whose structure the method changes, '?' for a name that is not a literal
     */
    private function checkMethod(ClassMethod $method) : array
    {
        $finder = new NodeFinder();
        $where = $method->name->toString() . '() line ';
        $variables = $this->tableVariables($method);
        $tables = [];

        foreach ($finder->findInstanceOf($method->stmts ?? [], MethodCall::class) as $call) {
            $name = $call->name instanceof Node\Identifier ? $call->name->toString() : '';
            $line = $where . $call->getStartLine();

            if ($this->isVariable($call->var, 'schema')) {
                if ($name === 'getTables') {
                    $tables[] = '?';
                } elseif (in_array($name, self::SCHEMA_TABLE_METHODS, true)) {
                    $tables[] = $this->stringValue($this->argument($call, 0, 'name')) ?? '?';

                    if ($name === 'renameTable') {
                        $tables[] = $this->stringValue($this->argument($call, 1, 'newName')) ?? '?';
                    }
                }
            }

            if (isset(self::NAMED_METHODS[$name])) {
                $this->checkName($call, $name, $this->tableOf($call->var, $variables), $line);
            }

            if ($name === 'renameIndex') {
                $newName = $this->stringValue($this->argument($call, 1, 'newIndexName'));

                if ($newName !== null && preg_match(self::NAME_PATTERN, $newName) !== 1) {
                    $this->migrationViolations[self::RULE_NAMING][] = $line . ': renameIndex() to ' . $newName;
                }
            }

            if ($this->isSqlCall($call, $name)) {
                $tables = [...$tables, ...$this->checkSql($call, $method, $line)];
            }
        }

        return array_values(array_unique($tables));
    }

    private function checkName(MethodCall $call, string $method, ?string $table, string $line) : void
    {
        [$columnsPosition, $columnsName, $namePosition, $nameName] = self::NAMED_ARGUMENTS[$method];
        $name = $this->stringValue($this->argument($call, $namePosition, $nameName));
        $columns = $this->stringList($this->argument($call, $columnsPosition, $columnsName));

        if ($name === null) {
            $this->migrationViolations[self::RULE_NAMING][] = $line . ': ' . $method . '() without an explicit (literal) name';

            return;
        }

        if ($table === null || $columns === null) {
            if (preg_match(self::NAME_PATTERN, $name) !== 1 || !str_starts_with($name, self::NAMED_METHODS[$method] . '__')) {
                $this->migrationViolations[self::RULE_NAMING][] = $line . ': ' . $method . '() named ' . $name;
            }

            return;
        }

        // web-project (AGENTS.md "Names of indexes and foreign keys"): K__<Table>__<Columns>, the table without its schema
        // ("public.users" -> Users), the columns joined by one underscore with only the first letter capital (Email_created_at)
        $dot = strrpos($table, '.');
        $table = $dot === false ? $table : substr($table, $dot + 1);
        $expected = self::NAMED_METHODS[$method] . '__' . ucfirst($table) . '__' . ucfirst(implode('_', $columns));

        if ($name !== $expected) {
            $this->migrationViolations[self::RULE_NAMING][] = $line . ': ' . $method . '() named ' . $name . ', expected ' . $expected;
        }
    }

    /**
     * @return list<string> the tables the statement changes the structure of
     */
    private function checkSql(MethodCall $call, ClassMethod $method, string $line) : array
    {
        $sql = $this->sqlValue($this->argument($call, 0, 'sql'));

        if ($sql === null) {
            $this->migrationViolations[self::RULE_SCHEMA_API][] = $line . ': the SQL is not a literal, it cannot be checked';

            return ['?'];
        }

        $firstLine = trim(explode("\n", trim($sql))[0]);
        $statement = strtoupper(trim((string) preg_replace('~^(?:\s+|--[^\n]*\n|/\*.*?\*/)+~s', '', $sql)));

        if (preg_match('~^(?:INSERT|UPDATE|DELETE|SELECT|WITH)\b~', $statement) === 1) {
            return [];
        }

        $tables = [];

        if (preg_match_all('~\b(?:TABLE|ON)\s+(?:ONLY\s+|IF\s+(?:NOT\s+)?EXISTS\s+)*"?(\w+)~', $statement, $matches) > 0) {
            $tables = array_map(strtolower(...), $matches[1]);
        }

        if (!$this->isSpecialSql($statement)) {
            $this->migrationViolations[self::RULE_SCHEMA_API][] = $line . ': raw DDL ' . $firstLine;

            return $tables;
        }

        if (!$this->hasComment($call, $method)) {
            $this->migrationViolations[self::RULE_SCHEMA_API][] = $line . ': ' . $firstLine . ' needs a comment saying why the Schema API cannot do it';
        }

        if (preg_match('~^CREATE\s+(?:UNIQUE\s+)?INDEX\s+(?:CONCURRENTLY\s+)?(?:IF\s+NOT\s+EXISTS\s+)?"?(\w+)~', $statement, $match) === 1
            && preg_match('~^(?:K|UK)__~i', $match[1]) !== 1) {
            $this->migrationViolations[self::RULE_NAMING][] = $line . ': index ' . $match[1] . ' (K__<Table>__... / UK__<Table>__...)';
        }

        return $tables;
    }

    /**
     * What the Schema API cannot express: an extension, an expression / partial / non-btree index, a type conversion
     * with USING. A plain index on columns is NOT special (addIndex()).
     */
    private function isSpecialSql(string $statement) : bool
    {
        if (preg_match('~^(?:CREATE|DROP)\s+EXTENSION\b~', $statement) === 1 || preg_match('~^DROP\s+INDEX\b~', $statement) === 1) {
            return true;
        }

        if (preg_match('~^ALTER\s+TABLE\b.*\bTYPE\b.*\bUSING\b~s', $statement) === 1) {
            return true;
        }

        if (preg_match('~^CREATE\s+(?:UNIQUE\s+)?INDEX\b.*?\bON\s+(?:ONLY\s+)?\S+\s*(?:USING\s+(\w+)\s*)?\((.*)\)\s*(\bWHERE\b.*)?;?$~s', $statement, $match) !== 1) {
            return false;
        }

        if (($match[3] ?? '') !== '' || !in_array($match[1], ['', 'BTREE'], true)) {
            return true;
        }

        foreach (explode(',', $match[2]) as $column) {
            if (preg_match('~^\s*"?\w+"?(?:\s+(?:ASC|DESC))?\s*$~', $column) !== 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<string> $upTables
     * @param list<string> $downTables
     */
    private function checkDown(ClassMethod $down, array $upTables, array $downTables) : void
    {
        $statements = array_filter($down->stmts ?? [], fn (Node\Stmt $statement) : bool => !$statement instanceof Node\Stmt\Nop);

        if ($statements === []) {
            $this->migrationViolations[self::RULE_DOWN][] = 'down() is empty (a comment is not enough: revert, or throwIrreversibleMigrationException())';

            return;
        }

        foreach ((new NodeFinder())->findInstanceOf($statements, MethodCall::class) as $call) {
            if ($call->name instanceof Node\Identifier && $call->name->toString() === 'throwIrreversibleMigrationException') {
                return;
            }
        }

        // '?' (a name that is not a literal) cannot be compared, the one-table rule reports it
        $missing = array_diff($upTables, $downTables, ['?']);

        if ($missing !== []) {
            $this->migrationViolations[self::RULE_DOWN][] = 'down() does not touch ' . implode(', ', $missing) . ' changed by up()';
        }
    }

    /**
     * @return array<string, string> variable => table, from `$table = $schema->createTable('x')` / `getTable('x')`
     */
    private function tableVariables(ClassMethod $method) : array
    {
        $variables = [];

        foreach ((new NodeFinder())->findInstanceOf($method->stmts ?? [], Expr\Assign::class) as $assign) {
            $table = $this->tableOf($assign->expr, []);

            if ($assign->var instanceof Expr\Variable && is_string($assign->var->name) && $table !== null) {
                $variables[$assign->var->name] = $table;
            }
        }

        return $variables;
    }

    /**
     * @param array<string, string> $variables
     */
    private function tableOf(Expr $expression, array $variables) : ?string
    {
        if ($expression instanceof Expr\Variable) {
            return is_string($expression->name) ? $variables[$expression->name] ?? null : null;
        }

        if (!$expression instanceof MethodCall || !$expression->name instanceof Node\Identifier) {
            return null;
        }

        if ($this->isVariable($expression->var, 'schema')) {
            return in_array($expression->name->toString(), ['createTable', 'getTable'], true)
                ? $this->stringValue($this->argument($expression, 0, 'name'))
                : null;
        }

        // a fluent call of the table: $schema->getTable('x')->dropColumn('a')->addIndex(...)
        return $this->tableOf($expression->var, $variables);
    }

    private function isSqlCall(MethodCall $call, string $name) : bool
    {
        if ($name === 'addSql') {
            return $this->isVariable($call->var, 'this');
        }

        return in_array($name, ['executeStatement', 'executeQuery'], true)
            && $call->var instanceof Expr\PropertyFetch
            && $call->var->name instanceof Node\Identifier
            && $call->var->name->toString() === 'connection';
    }

    /**
     * The comment belongs to the statement of the call (the line(s) above it) or stands on the line of the call.
     */
    private function hasComment(MethodCall $call, ClassMethod $method) : bool
    {
        foreach ((new NodeFinder())->findInstanceOf($method->stmts ?? [], Node\Stmt::class) as $statement) {
            if ($statement->getStartFilePos() <= $call->getStartFilePos() && $call->getEndFilePos() <= $statement->getEndFilePos()
                && $statement->getComments() !== []) {
                return true;
            }
        }

        return false;
    }

    private function argument(MethodCall $call, int $position, string $name) : ?Expr
    {
        foreach ($call->getArgs() as $index => $argument) {
            if ($argument->name !== null ? $argument->name->toString() === $name : $index === $position) {
                return $argument->value;
            }
        }

        return null;
    }

    private function isVariable(Expr $expression, string $name) : bool
    {
        return $expression instanceof Expr\Variable && $expression->name === $name;
    }

    private function stringValue(?Expr $expression) : ?string
    {
        return $expression instanceof Node\Scalar\String_ ? $expression->value : null;
    }

    /**
     * @return list<string>|null
     */
    private function stringList(?Expr $expression) : ?array
    {
        if (!$expression instanceof Expr\Array_) {
            return null;
        }

        $values = [];

        foreach ($expression->items as $item) {
            $value = $this->stringValue($item->value);

            if ($value === null) {
                return null;
            }

            $values[] = $value;
        }

        return $values;
    }

    /**
     * The text of a literal SQL: a string, a heredoc / nowdoc or a concatenation of them; an interpolated part is `?`.
     */
    private function sqlValue(?Expr $expression) : ?string
    {
        if ($expression instanceof Node\Scalar\String_) {
            return $expression->value;
        }

        if ($expression instanceof Node\Scalar\InterpolatedString) {
            $sql = '';

            foreach ($expression->parts as $part) {
                $sql .= $part instanceof Node\InterpolatedStringPart ? $part->value : '?';
            }

            return $sql;
        }

        if ($expression instanceof Expr\BinaryOp\Concat) {
            $left = $this->sqlValue($expression->left);
            $right = $this->sqlValue($expression->right);

            return $left === null || $right === null ? null : $left . $right;
        }

        return null;
    }
}
