<?php

namespace Lomkit\Rest\Text\Engine\Groups;

use LogicException;
use Lomkit\Rest\Fields\Filtering\FilterOperator;

/** @internal */
final readonly class TypesenseFilter
{
    public static function of(FilterTree|FilterLeaf $node): string
    {
        if ($node instanceof FilterLeaf) {
            return self::leaf($node);
        }

        return '('.implode($node->any ? ' || ' : ' && ', array_map(self::of(...), $node->members)).')';
    }

    private static function leaf(FilterLeaf $leaf): string
    {
        return match ($leaf->operator) {
            FilterOperator::In => $leaf->attribute.':=['.self::list($leaf).']',
            FilterOperator::NotIn => $leaf->attribute.':!=['.self::list($leaf).']',
            FilterOperator::NotEqual => $leaf->attribute.':!='.self::value($leaf->scalar()),
            FilterOperator::LessThan, FilterOperator::GreaterThan, FilterOperator::LessThanOrEqual, FilterOperator::GreaterThanOrEqual => $leaf->attribute.':'.$leaf->operator->value.self::value($leaf->scalar()),
            FilterOperator::Equal => $leaf->attribute.':='.self::value($leaf->scalar()),
            default => throw new LogicException("The [{$leaf->operator->value}] operator does not apply to a Typesense filter."),
        };
    }

    private static function list(FilterLeaf $leaf): string
    {
        return implode(', ', array_map(self::value(...), $leaf->members()));
    }

    private static function value(int|float|string|bool $value): string
    {
        return is_bool($value) ? ($value ? 'true' : 'false') : (string) $value;
    }
}
