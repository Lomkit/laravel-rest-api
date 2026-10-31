<?php

namespace Lomkit\Rest\Rules\Search;

use Illuminate\Validation\Rule;
use Lomkit\Rest\Fields\SortDirection;
use Lomkit\Rest\Rules\OneOf;
use Lomkit\Rest\Rules\RestRule;

class SearchSort extends RestRule
{
    /** @var list<string>|null */
    private ?array $names = null;

    /** @param list<string> $names */
    public function among(array $names): static
    {
        $this->names = $names;

        return $this;
    }

    /** @return array<string, list<mixed>> */
    #[\Override]
    public function buildValidationRules(string $attribute, mixed $value): array
    {
        $names = $this->names ?? $this->resource
            ->getFields($this->resource->context()->request)
            ->sortable()
            ->names();

        return [
            $attribute.'.field' => [
                new OneOf($names),
                'required',
                'string',
            ],
            $attribute.'.direction' => [
                'sometimes',
                Rule::enum(SortDirection::class),
            ],
        ];
    }
}
