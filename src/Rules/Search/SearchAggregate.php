<?php

namespace Lomkit\Rest\Rules\Search;

use Illuminate\Validation\Rule;
use Lomkit\Rest\Rules\OneOf;
use Lomkit\Rest\Rules\Resource\ResourceRelationOrNested;
use Lomkit\Rest\Rules\RestRule;

class SearchAggregate extends RestRule
{
    /** @return array<string, mixed> */
    public function buildValidationRules(string $attribute, mixed $value): array
    {
        $request = $this->resource->context()->request;

        $relation = is_array($value) ? ($value['relation'] ?? null) : null;
        $relationResource = is_string($relation) ? $this->resource->relation($relation)?->resource() : null;

        return array_merge(
            [
                $attribute.'.alias' => [
                    'nullable',
                    'string',
                ],
                $attribute.'.relation' => [
                    'required',
                    (new ResourceRelationOrNested)->setResource($this->resource),
                ],
                $attribute.'.type' => [
                    'required',
                    Rule::in(['count', 'min', 'max', 'avg', 'sum', 'exists']),
                ],
            ],
            ! is_null($relationResource) ? [
                $attribute.'.field' => [
                    'required_if:'.$attribute.'.type,min,max,avg,sum',
                    'prohibited_if:'.$attribute.'.type,count,exists',
                    new OneOf($relationResource->getFields($request)->names()),
                ],
                $attribute.'.filters.*' => [
                    (new SearchFilter)->setResource($relationResource),
                ],
            ] : []
        );
    }
}
