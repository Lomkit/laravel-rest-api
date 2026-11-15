<?php

namespace Lomkit\Rest\Text\Engine;

use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Builder;
use Lomkit\Rest\Exceptions\ConflictingListsException;
use Lomkit\Rest\Fields\Filtering\FilterOperator;
use Lomkit\Rest\Text\Engine\Profiles\EngineProfile;
use Lomkit\Rest\Text\Engine\Profiles\FiltersStringLists;

/** @internal */
final class IndexConditions
{
    /** @var list<array{string, FilterOperator, int|float|string|bool}> */
    private array $comparisons = [];

    /** @var array<string, list<int|float|string|bool>> */
    private array $memberships = [];

    /** @var array<string, list<int|float|string|bool>> */
    private array $exclusions = [];

    /** @var array<string, ?RestrictingExtension> */
    private array $sources = [];

    /** @var array<string, true> */
    private array $lists = [];

    private bool $nothing = false;

    public function __construct(private readonly string $resource) {}

    public function compare(string $attribute, FilterOperator $operator, int|float|string|bool $value): void
    {
        $this->comparisons[] = [$attribute, $operator, $value];
    }

    /** @param list<int|float|string|bool> $values */
    public function include(string $attribute, array $values, bool $list = false, ?RestrictingExtension $by = null): void
    {
        $this->mark($attribute, $list);
        $current = $this->memberships[$attribute] ?? null;

        if ($current !== null && ! $list) {
            $this->memberships[$attribute] = array_values(array_filter($current, fn (int|float|string|bool $value): bool => in_array($value, $values, true)));

            return;
        }

        $values = self::unique($values);

        if ($current !== null && self::holds($values, $current)) {
            return;
        }

        if ($current !== null && ! self::holds($current, $values)) {
            throw ConflictingListsException::memberships($this->resource, $attribute, $this->sources[$attribute] ?? null, $by);
        }

        $this->memberships[$attribute] = $values;
        $this->sources[$attribute] = $by;
    }

    /** @param list<int|float|string|bool> $values */
    public function exclude(string $attribute, array $values, bool $list = false): void
    {
        if ($values === []) {
            return;
        }

        $this->mark($attribute, $list);

        $this->exclusions[$attribute] = self::unique([...$this->exclusions[$attribute] ?? [], ...$values]);
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
        return $this->nothing || in_array([], $this->memberships, true);
    }

    /** @param Builder<Model> $builder */
    public function applyTo(Builder $builder, EngineProfile $profile): void
    {
        foreach ($this->comparisons as [$attribute, $operator, $value]) {
            $builder->where($attribute, $operator->value, $value);
        }

        $native = $profile instanceof FiltersStringLists ? $this->lists : [];

        foreach (array_diff_key($this->memberships, $native) as $attribute => $values) {
            $builder->whereIn($attribute, $values);
        }

        foreach (array_diff_key($this->exclusions, $native) as $attribute => $values) {
            $builder->whereNotIn($attribute, $values);
        }

        $memberships = array_intersect_key($this->memberships, $native);
        $exclusions = array_intersect_key($this->exclusions, $native);

        if ($profile instanceof FiltersStringLists && ($memberships !== [] || $exclusions !== [])) {
            $builder->options([...$builder->options, ...$profile->stringListOptions($memberships, $exclusions)]);
        }
    }

    private function mark(string $attribute, bool $list): void
    {
        if ($list) {
            $this->lists[$attribute] = true;
        }
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
