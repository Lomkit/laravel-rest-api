<?php

namespace Lomkit\Rest\Text\Engine;

use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Builder;
use Lomkit\Rest\Fields\Filtering\FilterOperator;
use Lomkit\Rest\Text\Engine\Groups\FilterLeaf;
use Lomkit\Rest\Text\Engine\Groups\FilterTree;
use Lomkit\Rest\Text\Engine\Profiles\EngineProfile;

/** @internal */
final class IndexConditions
{
    /** @var list<array{string, FilterOperator, int|float|string|bool}> */
    private array $comparisons = [];

    private readonly IndexMemberships $memberships;

    /** @var list<FilterTree|FilterLeaf> */
    private array $groups = [];

    private bool $nothing = false;

    public function __construct(string $resource, EngineProfile $profile)
    {
        $this->memberships = new IndexMemberships($resource, $profile);
    }

    public function compare(string $attribute, FilterOperator $operator, int|float|string|bool $value): void
    {
        $this->comparisons[] = [$attribute, $operator, $value];
    }

    /** @param list<int|float|string|bool> $values */
    public function include(string $attribute, array $values, bool $list = false, ?RestrictingExtension $by = null): void
    {
        $this->memberships->include($attribute, $values, $list, $by);
    }

    /** @param list<int|float|string|bool> $values */
    public function exclude(string $attribute, array $values, bool $list = false): void
    {
        $this->memberships->exclude($attribute, $values, $list);
    }

    public function group(FilterTree|FilterLeaf|bool $group): void
    {
        if ($group === false) {
            $this->nothing();

            return;
        }

        if ($group !== true) {
            $this->groups[] = $group;
        }
    }

    public function tree(?FilterTree $client): ?FilterTree
    {
        $members = [...$this->groups, ...$this->memberships->andedLeaves()];

        if ($client !== null) {
            $members[] = $client;
        }

        return match (true) {
            $members === [] => null,
            count($members) === 1 && $members[0] instanceof FilterTree => $members[0],
            default => new FilterTree(false, $members),
        };
    }

    public function nothing(): void
    {
        $this->nothing = true;
    }

    public function excludesEverything(): bool
    {
        return $this->nothing;
    }

    public function matchesNothing(): bool
    {
        return $this->nothing || $this->memberships->matchesNothing();
    }

    /** @param Builder<Model> $builder */
    public function applyTo(Builder $builder): void
    {
        foreach ($this->comparisons as [$attribute, $operator, $value]) {
            $builder->where($attribute, $operator->value, $value);
        }

        $this->memberships->applyTo($builder);
    }
}
