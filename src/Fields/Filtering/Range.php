<?php

namespace Lomkit\Rest\Fields\Filtering;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Contracts\Database\Query\Expression;
use Lomkit\Rest\Exceptions\InvalidFieldException;
use Lomkit\Rest\Rules\MaxLength;
use Lomkit\Rest\Validation\Lengths;

final readonly class Range implements Condition
{
    public function __construct(private bool $not) {}

    #[\Override]
    public function apply(Builder $query, Expression|string $column, mixed $value, string $boolean): void
    {
        if (! is_iterable($value)) {
            throw InvalidFieldException::invalidFilterValue($this->not ? 'not between' : 'between', (string) $query->getGrammar()->getValue($column), 'a list of two values', $value);
        }

        $query->whereBetween($column, $value, $boolean, $this->not);
    }

    #[\Override]
    public function valueRules(array $typeRules, Lengths $lengths): ValueRules
    {
        return new ValueRules(['present', 'array', 'list', 'size:2'], ['required', ...$typeRules, new MaxLength($lengths->values)]);
    }
}
