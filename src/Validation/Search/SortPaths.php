<?php

namespace Lomkit\Rest\Validation\Search;

use Closure;
use Lomkit\Rest\Fields\AggregateFunction;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Relations\MorphTo;
use Lomkit\Rest\Rules\AllowedValues;
use Lomkit\Rest\Rules\OneOf;

final readonly class SortPaths implements AllowedValues
{
    public function __construct(
        private Resource $resource,
        private OneOf $names,
    ) {}

    #[\Override]
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($this->accepts($value)) {
            return;
        }

        $fail('validation.in')->translate();
    }

    #[\Override]
    public function accepts(mixed $value): bool
    {
        if ($this->names->accepts($value)) {
            return true;
        }

        if (! is_string($value) || ! str_contains($value, '.')) {
            return false;
        }

        [$name, $field] = explode('.', $value, 2);
        $relation = $this->resource->relation($name);

        if ($relation === null || $relation->hasMultipleEntries() || $relation instanceof MorphTo) {
            return false;
        }

        $related = $relation->resource();
        $declared = $related
            ->getFields()
            ->find($field);

        return $declared !== null && AggregateFunction::Max->accepts($declared);
    }
}
