<?php

namespace Lomkit\Rest\Rules\Mutate;

use Illuminate\Support\Str;
use Lomkit\Rest\Rules\RestRule;

class MutateRelation extends RestRule
{
    public function __construct(protected int $depth = 0) {}

    /** @return array<string, mixed> */
    #[\Override]
    public function buildValidationRules(string $attribute, mixed $value): array
    {
        $relation = $this->resource->relation(Str::afterLast($attribute, '.'));

        if ($relation === null) {
            return [];
        }

        $entries = $relation->hasMultipleEntries() ? $attribute.'.*' : $attribute;

        return [
            $entries => (new Mutate($this->depth + 1, $relation))->setResource($relation->resource()),
            ...$relation->rules($relation->resource(), $attribute),
        ];
    }
}
