<?php

namespace Lomkit\Rest\Extensions\Contexts;

use Closure;
use Lomkit\Rest\Exceptions\InvalidEngineExtensionException;
use Lomkit\Rest\Exceptions\InvalidEngineQueryException;
use Lomkit\Rest\Fields\Filtering\FilterOperator;
use Lomkit\Rest\Text\Engine\EngineConditionGroup;
use Lomkit\Rest\Text\Engine\EngineQuery;
use Lomkit\Rest\Text\Engine\RestrictingExtension;

final readonly class EngineRestriction
{
    /** @internal */
    public function __construct(
        private EngineQuery $query,
        private RestrictingExtension $extension,
        private string $resource,
    ) {}

    public function where(string $attribute, FilterOperator|string $operator, mixed $value): self
    {
        return $this->apply(fn (): EngineQuery => $this->query->where($attribute, $operator, $value));
    }

    /** @param array<mixed> $values */
    public function whereIn(string $attribute, array $values): self
    {
        return $this->apply(fn (): EngineQuery => $this->query->restrictIn($attribute, $values, $this->extension));
    }

    /** @param array<mixed> $values */
    public function whereNotIn(string $attribute, array $values): self
    {
        return $this->apply(fn (): EngineQuery => $this->query->whereNotIn($attribute, $values));
    }

    /** @param Closure(EngineConditionGroup): mixed $group */
    public function whereAny(Closure $group): self
    {
        if (! $this->query->filtersWithGroups()) {
            throw InvalidEngineExtensionException::ungroupedRestriction($this->extension->name, $this->extension->class, $this->resource, $this->query->profile()->name());
        }

        return $this->apply(fn (): EngineQuery => $this->query->restrictGroup(EngineConditionGroup::compile($this->query->leaves(), true, $group)));
    }

    public function matchNothing(): self
    {
        $this->query->matchNothing();

        return $this;
    }

    private function apply(Closure $condition): self
    {
        $this->query->conditionedBy($this->extension);

        try {
            $condition();
        } catch (InvalidEngineQueryException $reason) {
            throw InvalidEngineExtensionException::refusedEngineCondition($this->extension->name, $this->extension->class, $this->resource, $reason);
        } finally {
            $this->query->conditionedBy(null);
        }

        return $this;
    }
}
