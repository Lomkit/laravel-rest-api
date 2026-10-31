<?php

namespace Lomkit\Rest\Rules\Search;

use Illuminate\Validation\Rule;
use Lomkit\Rest\Rules\RestRule;
use Lomkit\Rest\Text\TextBackend;
use Lomkit\Rest\Text\TextCapabilities;
use Lomkit\Rest\Text\TextMode;

class SearchText extends RestRule
{
    /** @return array<string, list<mixed>> */
    #[\Override]
    public function buildValidationRules(string $attribute, mixed $value): array
    {
        $text = $this->resource->context()->text($this->resource);
        $capabilities = $text->capabilities;

        if ($text->backend === TextBackend::None) {
            return [$attribute => ['prohibited']];
        }

        return [
            $attribute.'.value' => ['nullable', 'string', 'max:'.($capabilities->maxLength ?? TextCapabilities::MAX_LENGTH)],
            $attribute.'.mode' => $capabilities === null
                ? ['prohibited']
                : ['sometimes', 'string', Rule::enum(TextMode::class)->only($capabilities->modes)],
            $attribute.'.trashed' => ['prohibited'],
        ];
    }
}
