<?php

namespace Lomkit\Rest\Rules\Search;

use Illuminate\Validation\Rule;
use Lomkit\Rest\Rules\RestRule;
use Lomkit\Rest\Text\TextMode;

class SearchText extends RestRule
{
    /** @return array<string, list<mixed>> */
    #[\Override]
    public function buildValidationRules(string $attribute, mixed $value): array
    {
        $capabilities = $this->resource->context()->text($this->resource)->capabilities;

        if ($capabilities === null) {
            return [$attribute => ['prohibited']];
        }

        return [
            $attribute.'.value' => ['nullable', 'string', 'max:'.$capabilities->maxLength],
            $attribute.'.mode' => ['sometimes', 'string', Rule::enum(TextMode::class)->only($capabilities->modes)],
            $attribute.'.trashed' => ['prohibited'],
        ];
    }
}
