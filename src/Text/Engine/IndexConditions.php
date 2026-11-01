<?php

namespace Lomkit\Rest\Text\Engine;

use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Builder;
use Lomkit\Rest\Fields\Filtering\FilterOperator;

final class IndexConditions
{
    /** @var list<array{string, FilterOperator, int|float|string|bool}> */
    private array $comparisons = [];

    /** @var array<string, list<int|float|string|bool>> */
    private array $memberships = [];

    /** @var array<string, list<int|float|string|bool>> */
    private array $exclusions = [];

    public function compare(string $attribute, FilterOperator $operator, int|float|string|bool $value): void
    {
        $this->comparisons[] = [$attribute, $operator, $value];
    }

    /** @param list<int|float|string|bool> $values */
    public function include(string $attribute, array $values): void
    {
        $current = $this->memberships[$attribute] ?? null;

        $this->memberships[$attribute] = $current === null
            ? self::unique($values)
            : array_values(array_filter($current, fn (int|float|string|bool $value): bool => in_array($value, $values, true)));
    }

    /** @param list<int|float|string|bool> $values */
    public function exclude(string $attribute, array $values): void
    {
        if ($values === []) {
            return;
        }

        $this->exclusions[$attribute] = self::unique([...$this->exclusions[$attribute] ?? [], ...$values]);
    }

    public function matchesNothing(): bool
    {
        return in_array([], $this->memberships, true);
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
