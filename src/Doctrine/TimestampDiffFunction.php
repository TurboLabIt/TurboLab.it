<?php
namespace App\Doctrine;

use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\AST\Node;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\QueryException;
use Doctrine\ORM\Query\SqlWalker;
use Doctrine\ORM\Query\TokenType;


/**
 * MySQL TIMESTAMPDIFF(unit, start, end) for DQL: DATE_DIFF only counts whole calendar days
 * 📚 https://dev.mysql.com/doc/refman/8.4/en/date-and-time-functions.html#function_timestampdiff
 */
class TimestampDiffFunction extends FunctionNode
{
    const array UNITS = ['SECOND', 'MINUTE', 'HOUR', 'DAY', 'WEEK', 'MONTH', 'QUARTER', 'YEAR'];

    protected string $unit;
    protected Node $start;
    protected Node $end;


    public function parse(Parser $parser) : void
    {
        $parser->match(TokenType::T_IDENTIFIER);
        $parser->match(TokenType::T_OPEN_PARENTHESIS);

        $parser->match(TokenType::T_IDENTIFIER);
        $this->unit = strtoupper( $parser->getLexer()->token->value );

        if( !in_array($this->unit, static::UNITS) ) {
            throw QueryException::semanticalError("TIMESTAMPDIFF: unsupported unit {$this->unit}");
        }

        $parser->match(TokenType::T_COMMA);
        $this->start = $parser->ArithmeticPrimary();
        $parser->match(TokenType::T_COMMA);
        $this->end = $parser->ArithmeticPrimary();

        $parser->match(TokenType::T_CLOSE_PARENTHESIS);
    }


    public function getSql(SqlWalker $sqlWalker) : string
    {
        return
            'TIMESTAMPDIFF(' . $this->unit . ', ' .
                $this->start->dispatch($sqlWalker) . ', ' . $this->end->dispatch($sqlWalker) .
            ')';
    }
}
