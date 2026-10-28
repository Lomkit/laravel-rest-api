<?php

namespace Lomkit\Rest\Fields\Filtering;

use Illuminate\Contracts\Database\Eloquent\Builder;

final readonly class CaseInsensitivePattern implements Condition
{
    public function __construct(private bool $not) {}

    public function apply(Builder $query, string $column, mixed $value, string $boolean): void
    {
        $query->whereLike($column, $value, false, $boolean, $this->not);
    }

    public function valueRules(array $typeRules): ValueRules
    {
        return new ValueRules(['required', 'string']);
    }
}
