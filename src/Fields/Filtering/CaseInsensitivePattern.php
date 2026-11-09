<?php

namespace Lomkit\Rest\Fields\Filtering;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Contracts\Database\Query\Expression;
use Lomkit\Rest\Exceptions\InvalidFieldException;
use Lomkit\Rest\Validation\Lengths;

final readonly class CaseInsensitivePattern implements Condition
{
    public function __construct(private bool $not) {}

    #[\Override]
    public function apply(Builder $query, Expression|string $column, mixed $value, string $boolean): void
    {
        if (! is_string($value)) {
            throw InvalidFieldException::invalidFilterValue($this->not ? 'not ilike' : 'ilike', (string) $query->getGrammar()->getValue($column), 'a string', $value);
        }

        $query->whereLike($column, $value, false, $boolean, $this->not);
    }

    #[\Override]
    public function valueRules(array $typeRules, Lengths $lengths): ValueRules
    {
        return new ValueRules(['required', 'string', 'max:'.$lengths->pattern()]);
    }
}
