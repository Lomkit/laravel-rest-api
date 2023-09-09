<?php

namespace Lomkit\Rest\Concerns\Resource;

use Lomkit\Rest\Http\Requests\RestRequest;

trait ConfiguresRestParameters
{
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
     * Get nested fields by prefixing them with a given prefix.
     *
     * @param RestRequest $request
     * @param string      $prefix
     * @param array       $loadedRelations
     *
     * @return array
     */
    public function getNestedFields(RestRequest $request, string $prefix = '', array $loadedRelations = [])
    {
        if ($prefix !== '') {
            $prefix = $prefix.'.';
        }

        $fields = array_map(
            function ($field) use ($prefix) {
                return $prefix.$field;
            },
            $this->fields($request)
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
                ...$relation->resource()->getNestedFields($request, $prefix.$relation->relation, $loadedRelations),
                // We push the pivot fields if they exists
                ...collect(method_exists($relation, 'getPivotFields') ? $relation->getPivotFields() : [])
                        ->map(function ($field) use ($relation, $prefix) { return $prefix.$relation->relation.'.pivot.'.$field; })

            );
        }

        return $fields;
    }

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
}
