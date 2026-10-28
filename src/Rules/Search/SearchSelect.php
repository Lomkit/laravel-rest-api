<?php

namespace Lomkit\Rest\Rules\Search;

use Illuminate\Validation\Rule;
use Lomkit\Rest\Rules\RestRule;

class SearchSelect extends RestRule
{
    public function buildValidationRules(string $attribute, mixed $value): array
    {
        $request = $this->resource->context()->request;

        return [
            $attribute.'.field' => [
                Rule::in($this->resource->getFields($request)),
                'required',
                'string',
            ],
        ];
    }
}
