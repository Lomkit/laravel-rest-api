<?php

namespace Lomkit\Rest\Rules\Search;

use Illuminate\Validation\Rule;
use Lomkit\Rest\Fields\AggregateFunction;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Rules\Concerns\ReachesRelations;
use Lomkit\Rest\Rules\OneOf;
use Lomkit\Rest\Rules\Resource\ResourceRelationOrNested;
use Lomkit\Rest\Rules\RestRule;
use Lomkit\Rest\Validation\RuleCompiler;

class SearchAggregate extends RestRule
{
    use ReachesRelations;

    #[\Override]
    public function compile(RuleCompiler $into, string $attribute, mixed $value): void
    {
        $this->reachRelation($into, is_array($value) ? ($value['relation'] ?? null) : null, $attribute);

        parent::compile($into, $attribute, $value);
    }

    /** @return array<string, list<mixed>> */
    #[\Override]
    public function buildValidationRules(string $attribute, mixed $value): array
    {
        $aggregate = is_array($value) ? $value : [];
        $relation = $aggregate['relation'] ?? null;
        $relationResource = is_string($relation) ? $this->resource->relation($relation)?->resource() : null;

        $rules = [
            $attribute.'.alias' => ['nullable', 'string'],
            $attribute.'.relation' => ['required', (new ResourceRelationOrNested)->setResource($this->resource)],
            $attribute.'.type' => ['required', Rule::enum(AggregateFunction::class)],
        ];

        if ($relationResource === null) {
            return $rules;
        }

        return [
            ...$rules,
            $attribute.'.field' => $this->fieldRules($attribute, $relationResource, $aggregate['type'] ?? null),
            $attribute.'.filters.*' => [(new SearchFilter)->setResource($relationResource)],
        ];
    }

    /** @return list<mixed> */
    private function fieldRules(string $attribute, Resource $relationResource, mixed $type): array
    {
        $rules = [
            'required_if:'.$attribute.'.type,'.implode(',', array_column(AggregateFunction::needingField(), 'value')),
            'prohibited_if:'.$attribute.'.type,'.implode(',', array_column(AggregateFunction::takingNoField(), 'value')),
        ];

        $function = is_string($type) ? AggregateFunction::tryFrom($type) : null;

        if ($function === null || ! $function->needsField()) {
            return $rules;
        }

        $fields = $relationResource->getFields($this->resource->context()->request);

        return [...$rules, new OneOf($function->acceptedFieldNames($fields))];
    }
}
