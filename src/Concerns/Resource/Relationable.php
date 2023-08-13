<?php

namespace Lomkit\Rest\Concerns\Resource;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Relations\Relation;

trait Relationable
{
    public function relation($name) {
        $name = relation_without_pivot($name);

        $isSubRelation = Str::contains($name, '.');
        $relationName = $isSubRelation ? Str::before($name, '.') : $name;

        $relation = Arr::first($this->getRelations(app()->make(RestRequest::class)), function ($relation) use ($relationName) {
            return $relation->relation === $relationName;
        });

        if ($isSubRelation && Str::contains($nestedRelation = Str::after($name, '.'), '.')) {
            return $relation->resource()->relation($nestedRelation);
        }

        return $relation;
    }

    public function relationResource($name) {
        return $this->relation($name)?->resource();
    }

    public function nestedRelations(RestRequest $request, string $prefix = '', array $loadedRelations = []) {
        if ($prefix !== '') {
            $prefix = $prefix.'.';
        }

        $relations = [];

        foreach (
            collect($this->getRelations($request))
                ->filter(function($relation) use ($loadedRelations) {
                    return !in_array($relation->relation, $loadedRelations);
                })
            as $relation
        ) {
            $relations[$prefix . $relation->relation] = $relation;
            foreach ($relation->resource()->nestedRelations($request, $prefix.$relation->relation, array_merge($loadedRelations, [$relation->relation])) as $key => $value) {
                $relations[$key] = $value;
            };
        }

        return $relations;
    }

    public function relations(RestRequest $request): array {
        return [];
    }

    public function getRelations(RestRequest $request) {
        return array_map(function (Relation $relation) {
            return $relation->fromResource($this);
        }, $this->relations($request));
    }
}