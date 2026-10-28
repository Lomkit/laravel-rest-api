<?php

namespace Lomkit\Rest\Rules\Search;

use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Relations\Relation;

class SearchIncludeAlias implements DataAwareRule, ValidationRule
{
    /**
     * The data under validation.
     */
    protected array $data = [];

    /**
     * The resource instance.
     */
    protected Resource $resource;

    /**
     * The relation the alias is applied to.
     */
    protected string $relation;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // A nested relation is loaded through its parent, so there is no single level the alias
        // could key into. The equivalent nested include carries the alias unambiguously.
        if (Str::contains($this->relation, '.')) {
            $fail('The alias is not allowed on a nested relation, use a nested include instead.');

            return;
        }

        if ($value === config('rest.gates.key')) {
            $fail('The alias is reserved.');

            return;
        }

        if (in_array($value, $this->resource->getFields($this->resource->context()->request), true)) {
            $fail('The alias conflicts with a field of this resource.');

            return;
        }

        if (in_array($value, $this->relationNames(), true)) {
            $fail('The alias conflicts with a relation of this resource.');

            return;
        }

        // Two includes resolving to the same key would silently overwrite each other.
        if ($this->timesUsedAmongSiblings($attribute, $value) > 1) {
            $fail('The alias is already used by another include.');
        }
    }

    /**
     * Get the relation names exposed by the resource.
     */
    protected function relationNames(): array
    {
        return collect($this->resource->getRelations($this->resource->context()->request))
            ->map(function (Relation $relation) {
                return $relation->relation;
            })
            ->all();
    }

    /**
     * Count how many includes of the same level declare the given alias.
     */
    protected function timesUsedAmongSiblings(string $attribute, mixed $value): int
    {
        $includesKey = Str::beforeLast(Str::beforeLast($attribute, '.'), '.');

        return collect(Arr::get($this->data, $includesKey, []))
            ->filter(function ($include) use ($value) {
                return is_array($include) && ($include['alias'] ?? null) === $value;
            })
            ->count();
    }

    /**
     * Set the data under validation.
     */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    /**
     * Set the current resource.
     */
    public function setResource(Resource $resource): static
    {
        $this->resource = $resource;

        return $this;
    }

    /**
     * Set the relation the alias is applied to.
     */
    public function setRelation(string $relation): static
    {
        $this->relation = $relation;

        return $this;
    }
}
