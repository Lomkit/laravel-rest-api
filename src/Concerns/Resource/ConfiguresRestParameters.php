<?php

namespace Lomkit\Rest\Concerns\Resource;

use Illuminate\Support\Str;
use Lomkit\Rest\Exceptions\InvalidScopeException;
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
            // In case we are on a pivot we look for the relation pivot fields
            if (Str::before($field, '.') === 'pivot') {
                return method_exists($relation, 'getPivotFields') && in_array(Str::after($field, '.'), $relation->getPivotFields());
            }

            $fieldRelation = $this->relation(Str::before($field, '.'));

            return $fieldRelation?->resource()->isNestedField(Str::after($field, '.'), $fieldRelation) ?? false;
        }

        return $this->getFields($this->context()->request)->has($field);
    }

    /** @var list<Scope> */
    protected array $calculatedScopes;

    /** @return list<Scope> */
    public function scopes(RestRequest $request): array
    {
        return [];
    }

    /** @return list<Scope> */
    public function getScopes(RestRequest $request): array
    {
        return $this->calculatedScopes ??= self::ensureScopes($this->scopes($request), static::class.'::scopes()');
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

    /**
     * @param  array<mixed>  $scopes
     * @return list<Scope>
     */
    private static function ensureScopes(array $scopes, string $owner): array
    {
        $declared = [];

        foreach (array_values($scopes) as $index => $scope) {
            if (! $scope instanceof Scope) {
                throw InvalidScopeException::notAScope($owner, $index, $scope);
            }

            $declared[] = $scope;
        }

        return $declared;
    }

    /**
     * The calculated limits if already done in this request.
     */
    protected array $calculatedLimits;

    /**
     * The limits that could be provided.
     */
    public function limits(RestRequest $request): array
    {
        return [
            10,
            25,
            50,
        ];
    }

    /**
     * Get the resource's limits.
     */
    public function getLimits(RestRequest $request): array
    {
        return $this->calculatedLimits ?? ($this->calculatedLimits = $this->limits($request));
    }
}
