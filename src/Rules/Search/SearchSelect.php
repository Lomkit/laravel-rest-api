<?php

namespace Lomkit\Rest\Rules\Search;

use Lomkit\Rest\Rules\OneOf;
use Lomkit\Rest\Rules\RestRule;

class SearchSelect extends RestRule
{
    /** @return array<string, list<mixed>> */
    #[\Override]
    public function buildValidationRules(string $attribute, mixed $value): array
    {
        $fields = $this->resource->getFields($this->resource->context()->request);

        return [
            $attribute.'.field' => [
                new OneOf($fields->selectable()->names()),
                'required',
                'string',
            ],
        ];
    }
}
