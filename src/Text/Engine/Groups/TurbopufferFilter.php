<?php

namespace Lomkit\Rest\Text\Engine\Groups;

use Lomkit\Rest\Fields\Filtering\FilterOperator;

/** @internal */
final readonly class TurbopufferFilter
{
    private const array OPERATORS = ['=' => 'Eq', '!=' => 'NotEq', '<' => 'Lt', '<=' => 'Lte', '>' => 'Gt', '>=' => 'Gte', 'in' => 'In', 'not in' => 'NotIn'];

    /** @return list<mixed> */
    public static function of(FilterTree|FilterLeaf $node, string $key): array
    {
        if ($node instanceof FilterLeaf) {
            return [$node->attribute === $key ? 'id' : $node->attribute, self::OPERATORS[$node->operator->value] ?? 'Eq', $node->operator === FilterOperator::In || $node->operator === FilterOperator::NotIn ? $node->members() : $node->scalar()];
        }

        return [$node->any ? 'Or' : 'And', array_map(fn (FilterTree|FilterLeaf $member): array => self::of($member, $key), $node->members)];
    }
}
