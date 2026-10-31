<?php

namespace Lomkit\Rest\Rules\Search;

use Illuminate\Validation\Rule;
use Lomkit\Rest\Rules\OneOf;
use Lomkit\Rest\Rules\RestRule;

class ScoutFilter extends RestRule
{
    /** @return array<string, list<mixed>> */
    #[\Override]
    public function buildValidationRules(string $attribute, mixed $value): array
    {
        return [
            $attribute.'.field' => [
                'string',
                'required_without:'.$attribute.'.nested',
                new OneOf($this->resource->getScoutFields($this->resource->context()->request)),
            ],
            $attribute.'.nested' => ['prohibited'],
            $attribute.'.nested.*' => [(new ScoutFilter)->setResource($this->resource)],
            $attribute.'.operator' => ['string', Rule::in(['=', 'in', 'not in'])],
            $attribute.'.type' => ['prohibited'],
            $attribute.'.value' => ['exclude_if:'.$attribute.'.value,null', 'required_without:'.$attribute.'.nested'],
        ];
    }
}
