<?php

namespace Lomkit\Rest\Rules\Search;

use Illuminate\Validation\Rule;
use Lomkit\Rest\Rules\RestRule;

class SearchSort extends RestRule
{
    public function buildValidationRules(string $attribute, mixed $value): array
    {
        $request = $this->resource->context()->request;

        $fields = $request->isScoutMode() ?
            Rule::in($this->resource->getScoutFields($request)) :
            Rule::in($this->resource->getFields($request)->names());

        return [
            $attribute.'.field' => [
                $fields,
                'required',
                'string',
            ],
            $attribute.'.direction' => [
                'sometimes',
                Rule::in('desc', 'asc'),
            ],
        ];
    }
}
