<?php

namespace Lomkit\Rest\Rules\Search;

use Illuminate\Validation\Rule;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Rules\Resource\ResourceRelationOrNested;
use Lomkit\Rest\Rules\RestRule;

class SearchAggregate extends RestRule
{
    public function buildValidationRules(string $attribute, mixed $value): array
    {
        $request = app(RestRequest::class);

        $relationResource = $this->resource->relation($value['relation'])?->resource();

        return array_merge(
            [
                $attribute.'.*.alias' => [
                    'nullable',
                    'string',
                ],
                $attribute.'.relation' => [
                    'required',
                    (new ResourceRelationOrNested())->setResource($this->resource),
                ],
                $attribute.'.type' => [
                    Rule::in(['count', 'min', 'max', 'avg', 'sum', 'exists']),
                ],
            ],
            !is_null($relationResource) ? [
                $attribute.'.field' => [
                    'required_if:'.$attribute.'.type,min,max,avg,sum',
                    'prohibited_if:'.$attribute.'.type,count,exists',
                    Rule::in($relationResource->getFields($request)),
                ],
                $attribute.'.filters.*' => [
                    (new SearchFilter())->setResource($relationResource),
                ],
            ] : []
        );
    }
}
