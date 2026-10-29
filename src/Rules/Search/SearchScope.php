<?php

namespace Lomkit\Rest\Rules\Search;

use Lomkit\Rest\Fields\Field;
use Lomkit\Rest\Rules\Concerns\DependsOnTextSearch;
use Lomkit\Rest\Rules\OneOf;
use Lomkit\Rest\Rules\RestRule;
use Lomkit\Rest\Scopes\Scope;

class SearchScope extends RestRule
{
    use DependsOnTextSearch;

    /** @return array<string, list<mixed>> */
    public function buildValidationRules(string $attribute, mixed $value): array
    {
        if ($this->textSearch) {
            return [];
        }

        $request = $this->resource->context()->request;

        $names = array_map(fn (Scope $scope): string => $scope->name, $this->resource->getScopes($request));
        $rules = [$attribute.'.name' => ['required', 'string', new OneOf($names)]];
        $name = is_array($value) ? ($value['name'] ?? null) : null;
        $scope = is_string($name) ? $this->resource->scope($request, $name) : null;

        if ($scope === null) {
            return [...$rules, $attribute.'.parameters' => ['sometimes', 'array', 'list']];
        }

        $rules[$attribute.'.parameters'] = ['sometimes', 'array', 'list', 'max:'.count($scope->getFields())];

        foreach ($scope->getFields() as $position => $field) {
            $rules[$attribute.'.parameters.'.$position] = [...self::presenceRules($field), ...$field->getRules()->shared()];
        }

        return $rules;
    }

    /** @return list<string> */
    private static function presenceRules(Field $field): array
    {
        return match (true) {
            $field->hasDefault() && $field->isNullable() => [],
            $field->hasDefault() => ['filled'],
            $field->isNullable() => ['present'],
            default => ['required'],
        };
    }
}
