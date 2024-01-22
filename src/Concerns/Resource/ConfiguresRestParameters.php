<?php

namespace Lomkit\Rest\Concerns\Resource;

use Lomkit\Rest\Http\Requests\RestRequest;

trait ConfiguresRestParameters
{
    /**
     * The calculated fields if already done in this request.
     *
     * @var array
     */
    protected array $calculatedFields;

    /**
     * The fields that could be provided.
     *
     * @param RestRequest $request
     *
     * @return array
     */
    public function fields(RestRequest $request): array
    {
        return [];
    }

    /**
     * Get the resource's fields.
     *
     * @param \Lomkit\Rest\Http\Requests\RestRequest $request
     *
     * @return array
     */
    public function getFields(\Lomkit\Rest\Http\Requests\RestRequest $request): array
    {
        return $this->calculatedFields ?? ($this->calculatedFields = $this->fields($request));
    }

    /**
     * Get nested fields by prefixing them with a given prefix.
     *
     * @param RestRequest $request
     * @param string      $prefix
     * @param array       $loadedRelations
     *
     * @return array
     */
    public function getNestedFields(RestRequest $request, int $maxDepth = 3, string $prefix = '', array $loadedRelations = [])
    {
        if ($prefix !== '') {
            $prefix = $prefix.'.';
        }

        $fields = array_map(
            function ($field) use ($prefix) {
                return $prefix.$field;
            },
            $this->getFields($request)
        );

        foreach (
            collect($this->getRelations($request))
                ->filter(function ($relation) use ($loadedRelations) {
                    return !in_array($relation->relation, $loadedRelations);
                })
            as $relation
        ) {
            $loadedRelations[] = $relation->relation;
            array_push(
                $fields,
                ...($maxDepth > 0 ? $relation->resource()->getNestedFields($request, $maxDepth - 1, $prefix.$relation->relation, $loadedRelations) : []),
                // We push the pivot fields if they exists
                ...collect(method_exists($relation, 'getPivotFields') ? $relation->getPivotFields() : [])
                        ->map(function ($field) use ($relation, $prefix) { return $prefix.$relation->relation.'.pivot.'.$field; })
            );
        }

        return $fields;
    }

    /**
     * The calculated scopes if already done in this request.
     *
     * @var array
     */
    protected array $calculatedScopes;

    /**
     * The scopes that could be provided.
     *
     * @param RestRequest $request
     *
     * @return array
     */
    public function scopes(RestRequest $request): array
    {
        return [];
    }

    /**
     * Get the resource's scopes.
     *
     * @param \Lomkit\Rest\Http\Requests\RestRequest $request
     *
     * @return array
     */
    public function getScopes(\Lomkit\Rest\Http\Requests\RestRequest $request): array
    {
        return $this->calculatedScopes ?? ($this->calculatedScopes = $this->scopes($request));
    }

    /**
     * The calculated limits if already done in this request.
     *
     * @var array
     */
    protected array $calculatedLimits;

    /**
     * The limits that could be provided.
     *
     * @param RestRequest $request
     *
     * @return array
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
     *
     * @param \Lomkit\Rest\Http\Requests\RestRequest $request
     *
     * @return array
     */
    public function getLimits(\Lomkit\Rest\Http\Requests\RestRequest $request): array
    {
        return $this->calculatedLimits ?? ($this->calculatedLimits = $this->limits($request));
    }
}
