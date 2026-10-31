<?php

namespace Lomkit\Rest\Query\Expressions;

use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Grammar;

final readonly class TsMatch implements Expression
{
    /** @param non-empty-list<string> $columns */
    public function __construct(
        private array $columns,
        private string $language,
        private string $tsquery,
    ) {}

    #[\Override]
    public function getValue(Grammar $grammar): string
    {
        return self::document($grammar, $this->columns, $this->language).' @@ '.$this->tsquery;
    }

    /** @param non-empty-list<string> $columns */
    public static function document(Grammar $grammar, array $columns, string $language): string
    {
        $vectors = array_map(fn (string $column): string => "to_tsvector('{$language}', ".$grammar->wrap($column).')', $columns);

        return '('.implode(' || ', $vectors).')';
    }
}
