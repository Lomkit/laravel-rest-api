<?php

namespace Lomkit\Rest\Rules\Search;

use Illuminate\Validation\Rule;
use Lomkit\Rest\Rules\RestRule;

class SearchText extends RestRule
{
    /**
     * Build Laravel validation rules for a search input attribute.
     *
     * Returns rules that either prohibit the attribute when the resource is not model-searchable,
     * or validate a nested search payload structure when searchable:
     * - `<attribute>`: optionally present and must be an array.
     * - `<attribute>.value`: nullable string (the search text).
     * - `<attribute>.trashed`: must be one of `'with'` or `'only'`.
     *
     * @param string $attribute The root attribute name to validate (e.g. "search").
     * @param mixed $value Unused by this rule builder; present to match the rule interface.
     * @return array<string, array<int, mixed>> Laravel validation rules keyed by attribute path.
     */
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
