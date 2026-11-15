<?php

namespace Lomkit\Rest\Text\Engine;

use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Builder;
use Lomkit\Rest\Exceptions\InvalidEngineQueryException;
use Lomkit\Rest\Fields\Filtering\FilterOperator;

final class IndexConditions
{
    /** @var list<array{string, FilterOperator, int|float|string|bool}> */
    private array $comparisons = [];

    /** @var array<string, list<int|float|string|bool>> */
    private array $memberships = [];

    /** @var array<string, list<int|float|string|bool>> */
    private array $exclusions = [];

    private bool $nothing = false;

    public function compare(string $attribute, FilterOperator $operator, int|float|string|bool $value): void
    {
        $this->comparisons[] = [$attribute, $operator, $value];
    }

    /** @param list<int|float|string|bool> $values */
    public function include(string $attribute, array $values, bool $list = false): void
    {
        $current = $this->memberships[$attribute] ?? null;

        $this->memberships[$attribute] = match (true) {
            $current === null => self::unique($values),
            $list => self::narrower($attribute, $current, self::unique($values)),
            default => array_values(array_filter($current, fn (int|float|string|bool $value): bool => in_array($value, $values, true))),
        };
    }

    /** @param list<int|float|string|bool> $values */
    public function exclude(string $attribute, array $values): void
    {
        if ($values === []) {
            return;
        }

        $this->exclusions[$attribute] = self::unique([...$this->exclusions[$attribute] ?? [], ...$values]);
    }

    public function nothing(): void
    {
        $this->nothing = true;
    }

    public function matchesNothing(): bool
    {
        return $this->nothing || in_array([], $this->memberships, true);
    }

    /** @param Builder<Model> $builder */
    public function applyTo(Builder $builder): void
    {
        foreach ($this->comparisons as [$attribute, $operator, $value]) {
            $builder->where($attribute, $operator->value, $value);
        }

        foreach ($this->memberships as $attribute => $values) {
            $builder->whereIn($attribute, $values);
        }

        foreach ($this->exclusions as $attribute => $values) {
            $builder->whereNotIn($attribute, $values);
        }
    }

    /**
     * @param  list<int|float|string|bool>  $current
     * @param  list<int|float|string|bool>  $values
     * @return list<int|float|string|bool>
     */
    private static function narrower(string $attribute, array $current, array $values): array
    {
        return match (true) {
            self::holds($values, $current) => $current,
            self::holds($current, $values) => $values,
            default => throw InvalidEngineQueryException::conflictingListMemberships($attribute),
        };
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
