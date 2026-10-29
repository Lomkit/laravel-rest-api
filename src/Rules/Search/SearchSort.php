<?php

namespace Lomkit\Rest\Rules\Search;

use Illuminate\Validation\Rule;
use Lomkit\Rest\Rules\Concerns\DependsOnTextSearch;
use Lomkit\Rest\Rules\OneOf;
use Lomkit\Rest\Rules\RestRule;

class SearchSort extends RestRule
{
    use DependsOnTextSearch;

    /** @return array<string, mixed> */
    public function buildValidationRules(string $attribute, mixed $value): array
    {
        $request = $this->resource->context()->request;

        $fields = $this->textSearch ?
            new OneOf($this->resource->getScoutFields($request)) :
            new OneOf($this->resource->getFields($request)->names());

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
