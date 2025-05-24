<?php

namespace Lomkit\Rest\Rules\Search;

use Lomkit\Rest\Rules\RestRule;
use Lomkit\Rest\Rules\Search\Text\SearchTextValue;

class SearchText extends RestRule
{
    public function buildValidationRules(string $attribute, mixed $value): array
    {
        if (!$this->resource->isModelSearchable()) {
            return [
                $attribute => ['prohibited']
            ];
        }

        return [
            $attribute => ['sometimes', 'array'],
            $attribute.'.value' => ['string'],
        ];
    }
}