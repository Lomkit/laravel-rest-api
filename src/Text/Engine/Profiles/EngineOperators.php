<?php

namespace Lomkit\Rest\Text\Engine\Profiles;

use Lomkit\Rest\Fields\Field;
use Lomkit\Rest\Fields\Filtering\FilterOperator;
use Lomkit\Rest\Fields\Number;

final class EngineOperators
{
    /** @return list<FilterOperator> */
    public static function for(Field $attribute): array
    {
        $operators = [FilterOperator::Equal, FilterOperator::NotEqual, FilterOperator::In, FilterOperator::NotIn];

        if (! $attribute instanceof Number) {
            return $operators;
        }

        return [...$operators, FilterOperator::LessThan, FilterOperator::GreaterThan, FilterOperator::LessThanOrEqual, FilterOperator::GreaterThanOrEqual];
    }
}
