<?php

namespace Lomkit\Rest\Text\Engine;

use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Builder;
use Lomkit\Rest\Exceptions\ConflictingListsException;
use Lomkit\Rest\Fields\Filtering\FilterOperator;
use Lomkit\Rest\Text\Engine\Groups\FilterLeaf;
use Lomkit\Rest\Text\Engine\Profiles\EngineProfile;
use Lomkit\Rest\Text\Engine\Profiles\FiltersStringLists;
use Lomkit\Rest\Text\Engine\Profiles\FiltersWithGroups;

/** @internal */
final class IndexMemberships
{
    /** @var array<string, list<int|float|string|bool>> */
    private array $memberships = [];

    /** @var array<string, list<list<int|float|string|bool>>> */
    private array $andedLists = [];

    /** @var array<string, list<int|float|string|bool>> */
    private array $exclusions = [];

    /** @var array<string, ?RestrictingExtension> */
    private array $sources = [];

    /** @var array<string, true> */
    private array $lists = [];

    public function __construct(
        private readonly string $resource,
        private readonly EngineProfile $profile,
    ) {}

    /** @param list<int|float|string|bool> $values */
    public function include(string $attribute, array $values, bool $list, ?RestrictingExtension $by): void
    {
        if ($list) {
            $this->lists[$attribute] = true;
            $this->includeList($attribute, self::unique($values), $by);

            return;
        }

        $current = $this->memberships[$attribute] ?? null;
        $this->memberships[$attribute] = $current === null
            ? self::unique($values)
            : array_values(array_filter($current, fn (int|float|string|bool $value): bool => in_array($value, $values, true)));
    }

    /** @param list<int|float|string|bool> $values */
    public function exclude(string $attribute, array $values, bool $list): void
    {
        if ($values === []) {
            return;
        }

        if ($list) {
            $this->lists[$attribute] = true;
        }

        $this->exclusions[$attribute] = self::unique([...$this->exclusions[$attribute] ?? [], ...$values]);
    }

    public function matchesNothing(): bool
    {
        return in_array([], $this->memberships, true);
    }

    /** @return list<FilterLeaf> */
    public function andedLeaves(): array
    {
        $leaves = [];

        foreach ($this->andedLists as $attribute => $lists) {
            foreach ($lists as $values) {
                $leaves[] = new FilterLeaf($attribute, FilterOperator::In, $values, true);
            }
        }

        return $leaves;
    }

    /** @param Builder<Model> $builder */
    public function applyTo(Builder $builder): void
    {
        $native = $this->profile instanceof FiltersStringLists ? $this->lists : [];

        foreach (array_diff_key($this->memberships, $native) as $attribute => $values) {
            $builder->whereIn($attribute, $values);
        }

        foreach (array_diff_key($this->exclusions, $native) as $attribute => $values) {
            $builder->whereNotIn($attribute, $values);
        }

        $memberships = array_intersect_key($this->memberships, $native);
        $exclusions = array_intersect_key($this->exclusions, $native);

        if ($this->profile instanceof FiltersStringLists && ($memberships !== [] || $exclusions !== [])) {
            $builder->options([...$builder->options, ...$this->profile->stringListOptions($memberships, $exclusions)]);
        }
    }

    /** @param list<int|float|string|bool> $values */
    private function includeList(string $attribute, array $values, ?RestrictingExtension $by): void
    {
        $lists = isset($this->memberships[$attribute]) ? [$this->memberships[$attribute], ...$this->andedLists[$attribute] ?? []] : [];

        foreach ($lists as $list) {
            if (self::holds($values, $list)) {
                return;
            }
        }

        $narrowed = self::narrowed($lists, $values);

        if ($narrowed === null && $lists !== [] && ! $this->profile instanceof FiltersWithGroups) {
            throw ConflictingListsException::memberships($this->resource, $attribute, $this->sources[$attribute] ?? null, $by, $this->profile->name());
        }

        $kept = $narrowed ?? [...$lists, $values];

        if ($kept[0] === $values) {
            $this->sources[$attribute] = $by;
        }

        $this->memberships[$attribute] = $kept[0];
        $this->andedLists[$attribute] = array_slice($kept, 1);
    }

    /**
     * @param  list<list<int|float|string|bool>>  $lists
     * @param  list<int|float|string|bool>  $values
     * @return non-empty-list<list<int|float|string|bool>>|null
     */
    private static function narrowed(array $lists, array $values): ?array
    {
        $kept = [];
        $placed = false;

        foreach ($lists as $list) {
            if (! self::holds($list, $values)) {
                $kept[] = $list;
            } elseif (! $placed) {
                $kept[] = $values;
                $placed = true;
            }
        }

        return $placed ? $kept : null;
    }

    /**
     * @param  list<int|float|string|bool>  $outer
     * @param  list<int|float|string|bool>  $inner
     */
    private static function holds(array $outer, array $inner): bool
    {
        foreach ($inner as $value) {
            if (! in_array($value, $outer, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<int|float|string|bool>  $values
     * @return list<int|float|string|bool>
     */
    private static function unique(array $values): array
    {
        $unique = [];

        foreach ($values as $value) {
            if (! in_array($value, $unique, true)) {
                $unique[] = $value;
            }
        }

        return $unique;
    }
}
