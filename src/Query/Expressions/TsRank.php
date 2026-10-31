<?php

namespace Lomkit\Rest\Query\Expressions;

use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Grammar;

final readonly class TsRank implements Expression
{
    /** @param non-empty-list<non-empty-list<string>> $groups */
    public function __construct(
        private array $groups,
        private string $language,
        private string $tsquery,
    ) {}

    #[\Override]
    public function getValue(Grammar $grammar): string
    {
        $ranks = array_map(
            fn (array $columns): string => 'coalesce(ts_rank('.TsMatch::document($grammar, $columns, $this->language).', '.$this->tsquery.'), 0)',
            $this->groups,
        );

        return '('.implode(' + ', $ranks).')';
    }
}
