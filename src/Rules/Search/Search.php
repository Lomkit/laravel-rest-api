<?php

namespace Lomkit\Rest\Rules\Search;

use Illuminate\Validation\Rule;
use Lomkit\Rest\Rules\Concerns\DependsOnTextSearch;
use Lomkit\Rest\Rules\RestRule;

class Search extends RestRule
{
    use DependsOnTextSearch;

    /** @return array<string, mixed> */
    public function buildValidationRules(string $attribute, mixed $value): array
    {
        $request = $this->resource->context()->request;

        return [
            $attribute.'.limit' => ['sometimes', 'integer', Rule::in($this->resource->getLimits($request))],
            $attribute.'.page' => ['sometimes', 'integer', 'min:1'],
            $attribute.'.filters' => ['sometimes', 'array'],
            $attribute.'.gates' => ['sometimes', 'array', Rule::in(['viewAny', 'view', 'create', 'update', 'delete', 'restore', 'forceDelete'])],
            $attribute.'.filters.*' => (new SearchFilter)->setResource($this->resource)->forTextSearch($this->textSearch),
            $attribute.'.scopes' => ['sometimes', 'array', $this->textSearch ? 'prohibited' : ''],
            $attribute.'.scopes.*' => (new SearchScope)->setResource($this->resource)->forTextSearch($this->textSearch),
            $attribute.'.sorts' => ['sometimes', 'array'],
            $attribute.'.sorts.*' => (new SearchSort)->setResource($this->resource)->forTextSearch($this->textSearch),
            $attribute.'.selects' => ['sometimes', 'array'],
            $attribute.'.selects.*' => (new SearchSelect)->setResource($this->resource),
            $attribute.'.aggregates' => ['sometimes', 'array'],
            $attribute.'.aggregates.*' => (new SearchAggregate)->setResource($this->resource),
            $attribute.'.includes' => ['sometimes', 'array'],
            $attribute.'.includes.*' => (new SearchInclude)->setResource($this->resource),
            $attribute.'.instructions' => ['sometimes', 'array'],
            $attribute.'.instructions.*' => (new SearchInstruction)->setResource($this->resource)->forTextSearch($this->textSearch),
        ];
    }
}
