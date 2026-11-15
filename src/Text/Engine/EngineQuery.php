<?php

namespace Lomkit\Rest\Text\Engine;

use Lomkit\Rest\Exceptions\InvalidEngineQueryException;
use Lomkit\Rest\Fields\Filtering\FilterOperator;
use Lomkit\Rest\Fields\SortDirection;
use Lomkit\Rest\Query\Trashed;
use Lomkit\Rest\Text\TextQuery;

final class EngineQuery
{
    private const array COMPARISONS = [
        FilterOperator::Equal,
        FilterOperator::NotEqual,
        FilterOperator::LessThan,
        FilterOperator::GreaterThan,
        FilterOperator::LessThanOrEqual,
        FilterOperator::GreaterThanOrEqual,
    ];

    private readonly IndexConditions $conditions;

    /** @var list<array{string, SortDirection}> */
    private array $orders = [];

    /** @var array<string, mixed> */
    private array $options = [];

    public function __construct(
        private readonly EngineIndex $index,
        private readonly TextQuery $text,
        private readonly ?Trashed $trashed = null,
    ) {
        if ($trashed !== null && ! $index->model->softDeletes()) {
            throw InvalidEngineQueryException::trashedUnavailable($index->model->model()::class);
        }

        $this->conditions = new IndexConditions;
    }

    public function where(string $attribute, FilterOperator|string $operator, mixed $value): static
    {
        $declared = $this->attribute($attribute);
        $comparison = $operator instanceof FilterOperator ? $operator : FilterOperator::tryFrom($operator);

        if ($comparison === null || ! in_array($comparison, self::COMPARISONS, true) || ! $declared->supports($comparison)) {
            throw InvalidEngineQueryException::unsupportedOperator($attribute, $operator instanceof FilterOperator ? $operator->value : $operator);
        }

        $this->conditions->compare($attribute, $comparison, $declared->value($value));

        return $this;
    }

    /** @param array<mixed> $values */
    public function whereIn(string $attribute, array $values): static
    {
        $this->conditions->include($attribute, $this->members($attribute, FilterOperator::In, $values));

        return $this;
    }

    /** @param array<mixed> $values */
    public function whereNotIn(string $attribute, array $values): static
    {
        $this->conditions->exclude($attribute, $this->members($attribute, FilterOperator::NotIn, $values));

        return $this;
    }

    public function orderBy(string $attribute, SortDirection|string $direction = SortDirection::Asc): static
    {
        if (! $this->index->attributes->sorts($attribute)) {
            throw InvalidEngineQueryException::notSortable($attribute);
        }

        if (count($this->orders) >= $this->index->profile->sortKeys()) {
            throw InvalidEngineQueryException::tooManySorts($this->index->profile->sortKeys());
        }

        $this->orders[] = [$attribute, SortDirection::parse($direction)];

        return $this;
    }

    /** @param array<mixed> $options */
    public function options(array $options): static
    {
        $this->options = EngineOptions::merge($this->options, $options, $this->index->profile->ownedOptions());

        return $this;
    }

    public function page(int $perPage, int $page): EnginePage
    {
        $this->paging()->ensure($perPage, $page);

        if ($this->conditions->matchesNothing()) {
            return EnginePage::empty();
        }

        $builder = (new EngineBuilder($this->index, $this->text, $this->trashed))->build($this->conditions, $this->orders, $this->options);

        return $this->index->profile->page($builder, $this->index->model, $perPage, $page);
    }

    public function reachable(): ?int
    {
        return $this->index->profile->reachable($this->index->model);
    }

    public function paging(): EnginePaging
    {
        return new EnginePaging($this->index->profile);
    }

    private function attribute(string $name): IndexAttribute
    {
        return $this->index->attributes->find($name) ?? throw InvalidEngineQueryException::undeclaredAttribute($name);
    }

    /**
     * @param  array<mixed>  $values
     * @return list<int|float|string|bool>
     */
    private function members(string $attribute, FilterOperator $operator, array $values): array
    {
        $declared = $this->attribute($attribute);

        if (! $declared->supports($operator)) {
            throw InvalidEngineQueryException::unsupportedOperator($attribute, $operator->value);
        }

        return array_map(fn (mixed $value): int|float|string|bool => $declared->value($value), array_values($values));
    }
}
