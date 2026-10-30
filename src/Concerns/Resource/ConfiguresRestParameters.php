<?php

namespace Lomkit\Rest\Concerns\Resource;

use Illuminate\Support\Str;
use Lomkit\Rest\Contracts\PivotRelation;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Relations\Relation;
use Lomkit\Rest\Scopes\Scope;

trait ConfiguresRestParameters
{
    /**
     * Verify the field is correct including nested relations.
     *
     *
     * @return bool
     */
    public function isNestedField(string $field, ?Relation $relation = null)
    {
        if (Str::contains($field, '.')) {
            if (Str::before($field, '.') === 'pivot') {
                return $relation instanceof PivotRelation && $relation->getPivotFields()->has(Str::after($field, '.'));
            }

            $fieldRelation = $this->relation(Str::before($field, '.'));

            return $fieldRelation?->resource()->isNestedField(Str::after($field, '.'), $fieldRelation) ?? false;
        }

        return $this->getFields($this->context()->request)->has($field);
    }

    /** @return list<Scope> */
    public function scopes(RestRequest $request): array
    {
        return [];
    }

    /** @return list<Scope> */
    public function getScopes(RestRequest $request): array
    {
        return $this->context()->definition($this)->scopes();
    }

    public function scope(RestRequest $request, string $name): ?Scope
    {
        foreach ($this->getScopes($request) as $scope) {
            if ($scope->name === $name) {
                return $scope;
            }
        }

        return null;
    }

    /** @return list<int> */
    public function limits(RestRequest $request): array
    {
        return [
            10,
            25,
            50,
        ];
    }

    /** @return list<int> */
    public function getLimits(RestRequest $request): array
    {
        return $this->context()->definition($this)->limits();
    }
}
