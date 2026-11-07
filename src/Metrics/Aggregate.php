<?php

namespace Lomkit\Rest\Metrics;

enum Aggregate: string
{
    case Count = 'count';
    case Sum = 'sum';
    case Average = 'average';
    case Min = 'min';
    case Max = 'max';

    /** @return list<string> */
    public function functions(): array
    {
        return match ($this) {
            self::Count => ['count'],
            self::Sum => ['sum'],
            self::Average => ['sum', 'count'],
            self::Min => ['min'],
            self::Max => ['max'],
        };
    }

    /**
     * @param  list<mixed>  $values
     * @return list<int|float|null>
     */
    public function partials(array $values, bool $integer): array
    {
        return match ($this) {
            self::Count => [self::integer($values[0])],
            self::Sum => [$integer ? self::integer($values[0]) : self::float($values[0])],
            self::Average => [self::float($values[0]), self::integer($values[1])],
            self::Min, self::Max => [$values[0] === null ? null : ($integer ? self::integer($values[0]) : self::float($values[0]))],
        };
    }

    /**
     * @param  list<int|float|null>  $first
     * @param  list<int|float|null>  $second
     * @return list<int|float|null>
     */
    public function merge(array $first, array $second): array
    {
        return match ($this) {
            self::Count, self::Sum, self::Average => array_map(fn (int|float|null $one, int|float|null $two): int|float => ($one ?? 0) + ($two ?? 0), $first, $second),
            self::Min => [$first[0] === null || $second[0] === null ? $first[0] ?? $second[0] : min($first[0], $second[0])],
            self::Max => [$first[0] === null || $second[0] === null ? $first[0] ?? $second[0] : max($first[0], $second[0])],
        };
    }

    /** @param list<int|float|null> $partials */
    public function value(array $partials): int|float|null
    {
        if ($this !== self::Average) {
            return $partials[0];
        }

        return $partials[1] === 0 || $partials[1] === null ? null : $partials[0] / $partials[1];
    }

    private static function integer(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    private static function float(mixed $value): float
    {
        return is_numeric($value) ? (float) $value : 0.0;
    }
}
