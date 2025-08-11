<?php

namespace Lomkit\Rest\Rules\Search;

use Illuminate\Validation\Rule;
use Lomkit\Rest\Rules\RestRule;

class SearchText extends RestRule
{
    public function buildValidationRules(string $attribute, mixed $value): array
    {
        if (!$this->resource->isModelSearchable()) {
            return [
                $attribute => ['prohibited'],
            ];
        }

        return [
            $attribute            => ['sometimes', 'array'],
            $attribute.'.value'   => ['nullable', 'string'],
            $attribute.'.trashed' => [
                Rule::in('with', 'only'),
            ],
        ];
    }
}
