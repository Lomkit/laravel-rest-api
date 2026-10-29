<?php

namespace Lomkit\Rest\Rules\Search;

use Lomkit\Rest\Rules\OneOf;
use Lomkit\Rest\Rules\RestRule;

class SearchSelect extends RestRule
{
    /** @return array<string, list<mixed>> */
    public function buildValidationRules(string $attribute, mixed $value): array
    {
        $request = $this->resource->context()->request;

        return [
            $attribute.'.field' => [
                new OneOf($this->resource->getFields($request)->names()),
                'required',
                'string',
            ],
        ];
    }
}
