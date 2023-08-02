<?php

namespace Lomkit\Rest\Concerns\Resource;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;

trait ConfiguresRestParameters
{
    public function exposedFields(RestRequest $request) {
        return [];
    }

    public function getNestedExposedFields(RestRequest $request, string $prefix = '', array $loadedRelations = []) {
        if ($prefix !== '') {
            $prefix = $prefix.'.';
        }

        $exposedFields = array_map(
            function ($exposedField) use ($prefix) {
                return $prefix.$exposedField;
            },
            $this->exposedFields($request)
        );

        foreach (
            collect($this->getRelations($request))
                ->filter(function($relation) use ($loadedRelations) {
                    return !in_array($relation->relation, $loadedRelations);
                })
            as $relation
        ) {
            $loadedRelations[] = $relation->relation;
            array_push(
                $exposedFields,
                ...$relation->resource()->getNestedExposedFields($request, $prefix.$relation->relation,$loadedRelations),
                // We push the pivot fields if they exists
                ...(
                    collect(method_exists($relation, 'getPivotFields') ? $relation->getPivotFields() : [])
                        ->map(function ($field) use ($relation, $prefix) { return $prefix.$relation->relation.'.pivot.'.$field; })
                )
            );
        }

        return $exposedFields;
    }

    public function exposedScopes(RestRequest $request) {
        return [];
    }

    public function exposedLimits(RestRequest $request) {
        return [
            10,
            25,
            50
        ];
    }
}