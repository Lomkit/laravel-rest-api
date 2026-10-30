<?php

namespace Lomkit\Rest\Rules\Search;

use Illuminate\Validation\Rule;
use Lomkit\Rest\Fields\SortDirection;
use Lomkit\Rest\Rules\Concerns\DependsOnTextSearch;
use Lomkit\Rest\Rules\OneOf;
use Lomkit\Rest\Rules\RestRule;

class SearchSort extends RestRule
{
    use DependsOnTextSearch;

    /** @return array<string, list<mixed>> */
    #[\Override]
    public function buildValidationRules(string $attribute, mixed $value): array
    {
        $request = $this->resource->context()->request;

        $fields = $this->textSearch ?
            $this->resource->getScoutFields($request) :
            $this->resource->getFields($request)->sortable()->names();

        return [
            $attribute.'.field' => [
                new OneOf($fields),
                'required',
                'string',
            ],
            $attribute.'.direction' => [
                'sometimes',
                Rule::enum(SortDirection::class),
            ],
        ];
    }
}
