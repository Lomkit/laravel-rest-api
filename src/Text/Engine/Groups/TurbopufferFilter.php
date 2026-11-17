<?php

namespace Lomkit\Rest\Text\Engine\Groups;

use LogicException;
use Lomkit\Rest\Fields\Filtering\FilterOperator;

/** @internal */
final readonly class TurbopufferFilter
{
    private const array OPERATORS = ['=' => 'Eq', '!=' => 'NotEq', '<' => 'Lt', '<=' => 'Lte', '>' => 'Gt', '>=' => 'Gte', 'in' => 'In', 'not in' => 'NotIn'];

    private const array LIST_OPERATORS = ['in' => 'ContainsAny', 'not in' => 'NotContainsAny'];

    /** @return list<mixed> */
    public static function of(FilterTree|FilterLeaf $node, string $key): array
    {
        if ($node instanceof FilterLeaf) {
            $operator = ($node->list ? self::LIST_OPERATORS : self::OPERATORS)[$node->operator->value] ?? throw new LogicException("The [{$node->operator->value}] operator does not apply to a Turbopuffer filter.");

            return [$node->attribute === $key ? 'id' : $node->attribute, $operator, $node->operator === FilterOperator::In || $node->operator === FilterOperator::NotIn ? $node->members() : $node->scalar()];
        }

        return [$node->any ? 'Or' : 'And', array_map(fn (FilterTree|FilterLeaf $member): array => self::of($member, $key), $node->members)];
    }
}
