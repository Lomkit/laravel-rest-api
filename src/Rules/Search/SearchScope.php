<?php

namespace Lomkit\Rest\Rules\Search;

use Illuminate\Validation\Rule;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Rules\RestRule;

class SearchScope extends RestRule
{
    public function buildValidationRules(string $attribute, mixed $value): array
    {
        $request = app(RestRequest::class);

        if ($request->isScoutMode()) {
            return [];
        }

        return [
            $attribute.'.name' => [
                Rule::in($this->resource->getScopes($request)),
                'required',
                'string',
            ],
            $attribute.'.parameters' => [
                'sometimes',
                'array',
            ],
        ];
    }
}
