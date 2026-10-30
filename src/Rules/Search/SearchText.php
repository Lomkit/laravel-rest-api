<?php

namespace Lomkit\Rest\Rules\Search;

use Illuminate\Validation\Rule;
use Lomkit\Rest\Rules\Concerns\DependsOnTextSearch;
use Lomkit\Rest\Rules\RestRule;

class SearchText extends RestRule
{
    use DependsOnTextSearch;

    /** @return array<string, list<mixed>> */
    #[\Override]
    public function buildValidationRules(string $attribute, mixed $value): array
    {
        if (! $this->resource->isModelSearchable()) {
            return [
                $attribute => ['prohibited'],
            ];
        }

        return [
            $attribute.'.value' => ['nullable', 'string', 'max:255'],
            $attribute.'.trashed' => $this->textSearch ? [Rule::in('with', 'only')] : ['prohibited'],
        ];
    }
}
