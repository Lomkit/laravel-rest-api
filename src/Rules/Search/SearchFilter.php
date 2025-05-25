<?php

namespace Lomkit\Rest\Rules\Search;

use Illuminate\Validation\Rule;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Rules\Resource\ResourceFieldOrNested;
use Lomkit\Rest\Rules\RestRule;

class SearchFilter extends RestRule
{
    public function buildValidationRules(string $attribute, mixed $value): array
    {
        $request = app(RestRequest::class);
        $isScoutMode = $request->isScoutMode();

        $fieldsValidation = $isScoutMode ?
            Rule::in($this->resource->getScoutFields($request)) :
            (new ResourceFieldOrNested())->setResource($this->resource);

        $allowedOperators = $isScoutMode ?
            ['=', 'in', 'not in'] :
            ['=', '!=', '>', '>=', '<', '<=', 'like', 'not like', 'in', 'not in'];

        return [
            $attribute.'.field' => [
                'string',
                'required_without:'.$attribute.'.nested',
                $fieldsValidation,
            ],
            $attribute.'.nested' => !$isScoutMode ? [
                'sometimes',
                'prohibits:'.$attribute.'.field,operator,value',
                'array',
            ] : [
                'prohibited',
            ],
            $attribute.'.nested.*.nested' => [
                'prohibited',
            ],
            $attribute.'.nested.*' => [
                (new SearchFilter())->setResource($this->resource),
            ],
            $attribute.'.operator' => [
                'string',
                Rule::in($allowedOperators),
            ],
            $attribute.'.type' => !$isScoutMode ? [
                'sometimes',
                Rule::in(['or', 'and']),
            ] : [
                'prohibited',
            ],
            $attribute.'.value' => [
                'exclude_if:'.$attribute.'.value,null',
                'required_without:'.$attribute.'.nested',
            ],
        ];
    }
}
