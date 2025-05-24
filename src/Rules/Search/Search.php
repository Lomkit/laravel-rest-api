<?php

namespace Lomkit\Rest\Rules\Search;

use Illuminate\Validation\Rule;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Rules\RestRule;

class Search extends RestRule
{
    public function buildValidationRules(string $attribute, mixed $value): array
    {
        $request = app(RestRequest::class);
        $isScoutMode = $request->isScoutMode();

        return [
            $attribute.'.limit' => ['sometimes', 'integer', Rule::in($this->resource->getLimits($request))],
            $attribute.'page'  => ['sometimes', 'integer'],
            $attribute.'.filters' => ['sometimes', 'array'],
            $attribute.'gates' => ['sometimes', 'array', Rule::in(['viewAny', 'view', 'create', 'update', 'delete', 'restore', 'forceDelete'])],
            $attribute.'.filters.*' => (new SearchFilter)->setResource($this->resource),
            $attribute.'.scopes' => ['sometimes', 'array', $isScoutMode ? 'prohibited' : ''],
            $attribute.'.scopes.*' => (new SearchScope)->setResource($this->resource),
            $attribute.'.sorts' => ['sometimes', 'array'],
            $attribute.'.sorts.*' => (new SearchSort)->setResource($this->resource),
            $attribute.'.selects' => ['sometimes', 'array'],
            $attribute.'.selects.*' => (new SearchSelect)->setResource($this->resource),
            $attribute.'.aggregates' => ['sometimes', 'array'],
            $attribute.'.aggregates.*' => (new SearchAggregate)->setResource($this->resource),
            $attribute.'.includes' => ['sometimes', 'array'],
            $attribute.'.includes.*' => (new SearchInclude)->setResource($this->resource),
            $attribute.'.instructions' => ['sometimes', 'array'],
            $attribute.'.instructions.*' => (new SearchInstruction)->setResource($this->resource),
            $attribute.'.text' => ['sometimes', 'array', (new SearchText)->setResource($this->resource)],
        ];
    }
}