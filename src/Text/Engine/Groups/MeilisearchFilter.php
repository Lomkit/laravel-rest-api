<?php

namespace Lomkit\Rest\Text\Engine\Groups;

use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Builder;
use LogicException;
use Lomkit\Rest\Fields\Filtering\FilterOperator;

/** @internal */
final readonly class MeilisearchFilter
{
    public static function of(FilterTree|FilterLeaf $node): string
    {
        if ($node instanceof FilterLeaf) {
            return self::leaf($node->attribute, $node->operator, $node->value);
        }

        return '('.implode($node->any ? ' OR ' : ' AND ', array_map(self::of(...), $node->members)).')';
    }

    /** @param Builder<Model> $builder */
    public static function flat(Builder $builder): string
    {
        $conditions = [];

        foreach ($builder->wheres as $where) {
            if (is_array($where) && is_string($where['field'] ?? null)) {
                $conditions[] = self::where($where['field'], is_string($where['operator'] ?? null) ? $where['operator'] : '=', $where['value'] ?? null);
            }
        }

        foreach ($builder->whereIns as $attribute => $values) {
            $conditions[] = self::leaf((string) $attribute, FilterOperator::In, array_map(self::scalar(...), is_array($values) ? array_values($values) : []));
        }

        foreach ($builder->whereNotIns as $attribute => $values) {
            $conditions[] = self::leaf((string) $attribute, FilterOperator::NotIn, array_map(self::scalar(...), is_array($values) ? array_values($values) : []));
        }

        return implode(' AND ', $conditions);
    }

    /** @param int|float|string|bool|list<int|float|string|bool> $value */
    private static function leaf(string $attribute, FilterOperator $operator, int|float|string|bool|array $value): string
    {
        return match ($operator) {
            FilterOperator::In => $attribute.' IN ['.self::list($value).']',
            FilterOperator::NotIn => $attribute.' NOT IN ['.self::list($value).']',
            FilterOperator::Equal, FilterOperator::NotEqual, FilterOperator::LessThan, FilterOperator::GreaterThan, FilterOperator::LessThanOrEqual, FilterOperator::GreaterThanOrEqual => $attribute.$operator->value.self::value(is_array($value) ? '' : $value),
            default => throw new LogicException("The [{$operator->value}] operator does not apply to a Meilisearch filter."),
        };
    }

    private static function where(string $attribute, string $operator, mixed $value): string
    {
        return match (true) {
            $value instanceof BackedEnum => $attribute.$operator.$value->value,
            $value === null => $attribute.($operator === '!=' ? ' IS NOT NULL' : ' IS NULL'),
            default => $attribute.$operator.self::value(self::scalar($value)),
        };
    }

    /** @param int|float|string|bool|list<int|float|string|bool> $values */
    private static function list(int|float|string|bool|array $values): string
    {
        return implode(', ', array_map(fn (int|float|string|bool $value): string => match (true) {
            is_bool($value) => $value ? 'true' : 'false',
            filter_var($value, FILTER_VALIDATE_INT) !== false => (string) $value,
            default => self::quoted((string) $value),
        }, is_array($values) ? $values : [$values]));
    }

    private static function value(int|float|string|bool $value): string
    {
        return match (true) {
            is_bool($value) => $value ? 'true' : 'false',
            is_string($value) && ! is_numeric($value) => self::quoted($value),
            default => (string) $value,
        };
    }

    private static function quoted(string $value): string
    {
        return '"'.addcslashes($value, '"\\').'"';
    }

    private static function scalar(mixed $value): int|float|string|bool
    {
        return is_int($value) || is_float($value) || is_string($value) || is_bool($value) ? $value : (string) json_encode($value);
    }
}
