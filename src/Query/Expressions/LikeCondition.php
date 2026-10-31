<?php

namespace Lomkit\Rest\Query\Expressions;

use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Grammar;
use Illuminate\Database\Query\Grammars\PostgresGrammar;

final readonly class LikeCondition implements Expression
{
    private const string ESCAPE = '!';

    public function __construct(private string $column) {}

    public static function containing(string $needle): string
    {
        return '%'.strtr($needle, [
            self::ESCAPE => self::ESCAPE.self::ESCAPE,
            '%' => self::ESCAPE.'%',
            '_' => self::ESCAPE.'_',
            '[' => self::ESCAPE.'[',
        ]).'%';
    }

    #[\Override]
    public function getValue(Grammar $grammar): string
    {
        $operator = $grammar instanceof PostgresGrammar ? 'ilike' : 'like';

        return $grammar->wrap($this->column).' '.$operator." ? escape '".self::ESCAPE."'";
    }
}
