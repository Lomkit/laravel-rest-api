<?php

namespace Lomkit\Rest\Rules\Search;

use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Lomkit\Rest\Http\Resource;

final class SearchIncludeAlias implements DataAwareRule, ValidationRule
{
    /** @var array<array-key, mixed> */
    private array $data = [];

    public function __construct(
        private readonly Resource $resource,
        private readonly string $relation,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $message = $this->conflict($attribute, $value);

        if ($message !== null) {
            $fail($message);
        }
    }

    /** @param array<array-key, mixed> $data */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    private function conflict(string $attribute, mixed $value): ?string
    {
        $request = $this->resource->context()->request;

        return match (true) {
            str_contains($this->relation, '.') => 'The :attribute is not allowed on a nested relation, use a nested include instead.',
            $value === config('rest.gates.key') => 'The :attribute is reserved.',
            in_array($value, $this->resource->getFields($request)->names(), true) => 'The :attribute conflicts with a field of this resource.',
            in_array($value, array_column($this->resource->getRelations($request), 'relation'), true) => 'The :attribute conflicts with a relation of this resource.',
            $this->timesUsedAmongSiblings($attribute, $value) > 1 => 'The :attribute is already used by another include.',
            default => null,
        };
    }

    private function timesUsedAmongSiblings(string $attribute, mixed $value): int
    {
        $includes = Arr::get($this->data, Str::beforeLast(Str::beforeLast($attribute, '.'), '.'));

        if (! is_array($includes)) {
            return 0;
        }

        return count(array_filter(array_column($includes, 'alias'), fn (mixed $alias): bool => $alias === $value));
    }
}
