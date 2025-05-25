<?php

namespace Lomkit\Rest\Rules\Mutate;

use Illuminate\Support\Str;
use Lomkit\Rest\Rules\RestRule;

class MutateRelation extends RestRule
{
    public function __construct(protected int $depth = 0)
    {
    }

    public function buildValidationRules(string $attribute, mixed $value): array
    {
        $relation = $this->resource->relation(Str::afterLast($attribute, '.'));

        if (is_null($relation)) {
            return [];
        }

        return (new Mutate(depth: ++$this->depth, relation: $relation))->setResource($relation->resource())->buildValidationRules($attribute, $value);
    }
}
