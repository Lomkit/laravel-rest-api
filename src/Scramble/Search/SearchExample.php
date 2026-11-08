<?php

namespace Lomkit\Rest\Scramble\Search;

use Lomkit\Rest\Fields\Field;
use Lomkit\Rest\Fields\Filtering\FilterOperator;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Scramble\FieldExample;

final class SearchExample
{
    /** @return array{search: array{filters: list<array{field: string, value: mixed}>}}|null */
    public static function of(Resource $resource): ?array
    {
        $fields = [];
        $filterable = $resource
            ->definition()
            ->fields()
            ->filterable();

        foreach ($filterable as $field) {
            if (in_array(FilterOperator::Equal, $field->filterOperators(), true)) {
                $fields[] = $field;
            }
        }

        $field = array_find($fields, fn (Field $field): bool => $field->getExamples() !== []) ?? $fields[0] ?? null;

        if ($field === null) {
            return null;
        }

        return ['search' => ['filters' => [['field' => $field->name, 'value' => FieldExample::of($field)]]]];
    }
}
