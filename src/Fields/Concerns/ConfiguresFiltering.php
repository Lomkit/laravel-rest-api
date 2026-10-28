<?php

namespace Lomkit\Rest\Fields\Concerns;

use Closure;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Lomkit\Rest\Contracts\Filter;
use Lomkit\Rest\Exceptions\InvalidFieldException;
use Lomkit\Rest\Fields\Filtering\FilterOperator;

trait ConfiguresFiltering
{
    /** @var bool|list<string>|null */
    private bool|array|null $filterable = null;

    /** @var (Closure(Builder, string, mixed): mixed)|Filter|null */
    private Closure|Filter|null $filterUsing = null;

    /** @param bool|list<string> $filterable */
    public function filterable(bool|array $filterable = true): static
    {
        $this->filterable = $filterable;

        return $this;
    }

    /** @param (Closure(Builder, string, mixed): mixed)|Filter $filter */
    public function filterUsing(Closure|Filter $filter): static
    {
        $this->filterUsing = $filter;

        return $this;
    }

    /** @return list<FilterOperator> */
    public function filterOperators(): array
    {
        $filterable = $this->filterable ?? $this->filterableByDefault();

        if ($filterable === false || (($this->isVirtual() || $this->isWriteOnly()) && $this->filterUsing === null)) {
            return [];
        }

        if ($filterable === true) {
            return $this->supportedOperators();
        }

        return array_map(fn (string $operator): FilterOperator => $this->supportedOperator($operator), $filterable);
    }

    public function isFilterable(): bool
    {
        return $this->filterOperators() !== [];
    }

    public function filter(Builder $query, FilterOperator|string $operator, mixed $value, string $boolean = 'and', ?string $column = null): void
    {
        $operator = FilterOperator::parse($operator);
        $filter = $this->filterUsing;

        if (! in_array($operator, $this->filterOperators(), true)) {
            throw InvalidFieldException::unsupportedOperator($this->name, $operator->value);
        }

        if ($filter === null) {
            $this->applyFilter($query, $operator, $value, $boolean, $column);

            return;
        }

        $query->where(function (Builder $query) use ($filter, $operator, $value): void {
            $filter instanceof Filter
                ? $filter->apply($query, $operator->value, $value)
                : $filter($query, $operator->value, $value);
        }, null, null, $boolean);
    }

    /** @return array<string, list<mixed>> */
    public function filterValueRules(FilterOperator|string $operator, string $attribute): array
    {
        return FilterOperator::parse($operator)->condition()->valueRules($this->baseRules())->for($attribute);
    }

    private function supportedOperator(string $operator): FilterOperator
    {
        $parsed = FilterOperator::parse($operator);

        if (! in_array($parsed, $this->supportedOperators(), true)) {
            throw InvalidFieldException::unsupportedOperator($this->name, $parsed->value);
        }

        return $parsed;
    }
}
