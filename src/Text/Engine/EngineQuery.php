<?php

namespace Lomkit\Rest\Text\Engine;

use Closure;
use Lomkit\Rest\Exceptions\InvalidEngineQueryException;
use Lomkit\Rest\Fields\Filtering\FilterOperator;
use Lomkit\Rest\Fields\SortDirection;
use Lomkit\Rest\Instructions\Instruction;
use Lomkit\Rest\Query\ExtensionRestrictions;
use Lomkit\Rest\Query\Trashed;
use Lomkit\Rest\Search\Totals\TotalsNode;
use Lomkit\Rest\Text\Engine\Groups\FilterLeaf;
use Lomkit\Rest\Text\Engine\Groups\FilterTree;
use Lomkit\Rest\Text\Engine\Profiles\EngineProfile;
use Lomkit\Rest\Text\Engine\Profiles\FiltersWithGroups;
use Lomkit\Rest\Text\TextQuery;

final class EngineQuery
{
    private readonly IndexConditions $conditions;

    private readonly EngineSorts $sorts;

    private ?FilterTree $tree = null;

    /** @var array<string, mixed> */
    private array $options = [];

    private EngineRead $read;

    private Instruction|RestrictingExtension|null $source = null;

    public function __construct(
        private readonly EngineIndex $index,
        private readonly ?TextQuery $text,
        private readonly ?Trashed $trashed = null,
    ) {
        if ($trashed !== null && ! $index->model->softDeletes()) {
            throw InvalidEngineQueryException::trashedUnavailable($index->model->model()::class);
        }

        $this->conditions = new IndexConditions($index->searchedClass(), $index->profile);
        $this->sorts = new EngineSorts($index, $text === null);
        $this->read = new EngineRead($index);
    }

    public function where(string $attribute, FilterOperator|string $operator, mixed $value): static
    {
        $declared = $this->index->attributes->declared($attribute);

        $this->conditions->compare($attribute, $declared->comparison($operator), $declared->value($value));

        return $this;
    }

    /** @param array<mixed> $values */
    public function whereIn(string $attribute, array $values): static
    {
        return $this->restrictIn($attribute, $values);
    }

    /**
     * @internal
     *
     * @param  array<mixed>  $values
     */
    public function restrictIn(string $attribute, array $values, ?RestrictingExtension $by = null): static
    {
        $declared = $this->leaves()->compared($attribute);

        $this->conditions->include($attribute, $declared->members(FilterOperator::In, $values), $declared->list, $by);

        return $this;
    }

    /** @param array<mixed> $values */
    public function whereNotIn(string $attribute, array $values): static
    {
        $declared = $this->leaves()->compared($attribute);

        $this->conditions->exclude($attribute, $declared->members(FilterOperator::NotIn, $values), $declared->list);

        return $this;
    }

    /** @param Closure(EngineConditionGroup): mixed $group */
    public function whereAny(Closure $group): static
    {
        return $this->filtersWithGroups() ? $this->restrictGroup(EngineConditionGroup::compile($this->leaves(), true, $group)) : throw InvalidEngineQueryException::ungroupedConditions($this->source, $this->index->searchedClass(), $this->index->profile->name());
    }

    public function filtersWithGroups(): bool
    {
        return $this->index->profile instanceof FiltersWithGroups;
    }

    public function orderBy(string $attribute, SortDirection|string $direction = SortDirection::Asc): static
    {
        $this->sorts->add($attribute, $direction);

        return $this;
    }

    /** @internal */
    public function whereGroups(FilterTree $tree): static
    {
        if (! $this->filtersWithGroups()) {
            throw InvalidEngineQueryException::ungroupedFilters($this->index->searchedClass(), $this->index->profile->name());
        }

        $this->tree = $tree;

        return $this;
    }

    /** @param array<mixed> $options */
    public function options(array $options): static
    {
        $this->options = EngineOptions::merge($this->options, $options, $this->index->ownedOptions());

        return $this;
    }

    public function page(int $perPage, int $page): EnginePage
    {
        $this->paging()->ensure($perPage, $page);

        if ($this->conditions->matchesNothing()) {
            return $this->read->empty();
        }

        return $this->read->page((new EngineBuilder($this->index, $this->text, $this->trashed))->build($this->conditions, $this->sorts->all(), $this->options, $this->conditions->tree($this->tree)), $perPage, $page);
    }

    /** @internal */
    public function totals(TotalsNode $node): static
    {
        $this->read = $this->read->totaling($node);

        return $this;
    }

    /** @internal */
    public function restrictGroup(FilterTree|FilterLeaf|bool $group): static
    {
        $this->conditions->group($group);

        return $this;
    }

    /** @internal */
    public function conditionedBy(Instruction|RestrictingExtension|null $source): void
    {
        $this->source = $source;
    }

    /** @internal */
    public function leaves(): IndexLeaves
    {
        return new IndexLeaves($this->index, $this->source);
    }

    /** @internal */
    public function matchNothing(): static
    {
        $this->conditions->nothing();

        return $this;
    }

    /** @internal */
    public function restrictRows(ExtensionRestrictions $rows): bool
    {
        return ($this->read = $this->read->restrictedBy($rows))->restricts();
    }

    /** @internal */
    public function engine(): object
    {
        return $this->index->engine();
    }

    /** @internal */
    public function profile(): EngineProfile
    {
        return $this->index->profile;
    }

    public function reachable(): ?int
    {
        return $this->conditions->excludesEverything() ? PHP_INT_MAX : $this->index->profile->reachable($this->index->model);
    }

    public function paging(): EnginePaging
    {
        return new EnginePaging($this->index->profile);
    }
}
