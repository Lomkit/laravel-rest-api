<?php

namespace Lomkit\Rest\Concerns\Resource;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Relations\Relation;

trait Relationable
{
    public function relation(string $name): ?Relation
    {
        $name = relation_without_pivot($name);

        $isSubRelation = Str::contains($name, '.');
        $relationName = $isSubRelation ? Str::before($name, '.') : $name;

        $relation = Arr::first($this->getRelations($this->context()->request), function ($relation) use ($relationName) {
            return $relation->relation === $relationName;
        });

        if ($isSubRelation) {
            $nestedRelation = Str::after($name, '.');

            return $relation?->resource()->relation($nestedRelation);
        }

        return $relation;
    }

    /** @return array<int, Relation> */
    public function relations(RestRequest $request): array
    {
        return [];
    }

    /** @return array<int, Relation> */
    public function getRelations(RestRequest $request): array
    {
        return $this->context()->definition($this)->relations();
    }
}
