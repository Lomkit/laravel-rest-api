<?php

namespace Lomkit\Rest\Query\Expressions;

use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Grammar;

final readonly class FullTextScore implements Expression
{
    /** @param non-empty-list<non-empty-list<string>> $groups */
    public function __construct(
        private array $groups,
        private bool $boolean,
    ) {}

    #[\Override]
    public function getValue(Grammar $grammar): string
    {
        $mode = $this->boolean ? 'in boolean mode' : 'in natural language mode';
        $scores = array_map(fn (array $group): string => 'match ('.$grammar->columnize($group).") against (? {$mode})", $this->groups);

        return '('.implode(' + ', $scores).')';
    }
}
