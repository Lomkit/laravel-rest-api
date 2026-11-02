<?php

namespace Lomkit\Rest\Validation\Search;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Lomkit\Rest\Http\Resource;

final readonly class IncludeAlias implements ValidationRule
{
    public function __construct(
        private Resource $resource,
        private string $relation,
        private int $uses,
    ) {}

    #[\Override]
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $message = $this->conflict($value);

        if ($message !== null) {
            $fail($message)->translate();
        }
    }

    private function conflict(mixed $value): ?string
    {
        $request = $this->resource->context()->request;

        return match (true) {
            str_contains($this->relation, '.') => 'The :attribute is not allowed on a nested relation, use a nested include instead.',
            $value === $this->resource->context()->config()->gatesKey => 'The :attribute is reserved.',
            in_array($value, $this->resource->getFields($request)->names(), true) => 'The :attribute conflicts with a field of this resource.',
            in_array($value, array_column($this->resource->getRelations($request), 'relation'), true) => 'The :attribute conflicts with a relation of this resource.',
            $this->uses > 1 => 'The :attribute is already used by another include.',
            default => null,
        };
    }
}
