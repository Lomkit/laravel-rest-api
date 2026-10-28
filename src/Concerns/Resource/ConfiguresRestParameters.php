<?php

namespace Lomkit\Rest\Concerns\Resource;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Str;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Relations\Relation;

trait ConfiguresRestParameters
{
    /**
     * The calculated fields if already done in this request.
     */
    protected array $calculatedFields;

    /**
     * The fields that could be provided.
     */
    public function fields(RestRequest $request): array
    {
        return [];
    }

    /**
     * Get the resource's fields.
     */
    public function getFields(RestRequest $request): array
    {
        return $this->calculatedFields ?? ($this->calculatedFields = $this->fields($request));
    }

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

        return in_array($field, $this->getFields(App::make(RestRequest::class)));
    }

    /**
     * The calculated scopes if already done in this request.
     */
    protected array $calculatedScopes;

    /**
     * The scopes that could be provided.
     */
    public function scopes(RestRequest $request): array
    {
        return [];
    }

    /**
     * Get the resource's scopes.
     */
    public function getScopes(RestRequest $request): array
    {
        return $this->calculatedScopes ?? ($this->calculatedScopes = $this->scopes($request));
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
