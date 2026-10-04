<?php declare(strict_types=1);

namespace App\Database\Functions;

use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\AST\Node;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\SqlWalker;
use Doctrine\ORM\Query\TokenType;

/**
 * DQL REGEXP(column, 'pattern') on PostgreSQL (the grids filter with `REGEXP(...) = true`): PostgreSQL has no REGEXP operator, so it becomes
 * `unaccent(column) ~* unaccent('pattern')` - ignores case AND accents like the old (MySQL era) collation the grids were written for
 * ("adminrole" finds "ÁdminRole"). The unaccent extension is created by a migration.
 * Compare it as `= true`: `~*` is a real boolean in PostgreSQL, so `= 1` would fail.
 */
class Regexp extends FunctionNode
{
    private ?Node $value = null;

    private ?Node $pattern = null;

    public function parse(Parser $parser) : void
    {
        $parser->match(TokenType::T_IDENTIFIER);
        $parser->match(TokenType::T_OPEN_PARENTHESIS);
        $this->value = $parser->StringPrimary();
        $parser->match(TokenType::T_COMMA);
        $this->pattern = $parser->StringPrimary();
        $parser->match(TokenType::T_CLOSE_PARENTHESIS);
    }

    public function getSql(SqlWalker $sqlWalker) : string
    {
        return sprintf(
            '(unaccent(CAST(%s AS TEXT)) ~* unaccent(CAST(%s AS TEXT)))',
            $this->value?->dispatch($sqlWalker) ?? '',
            $this->pattern?->dispatch($sqlWalker) ?? '',
        );
    }
}
