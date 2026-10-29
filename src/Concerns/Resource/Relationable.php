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

            return $relation->resource()->relation($nestedRelation);
        }

        return $relation;
    }

    /** @var array<int, Relation> */
    protected array $calculatedRelations;

    /** @return array<int, Relation> */
    public function relations(RestRequest $request): array
    {
        return [];
    }

    public function getRelations(RestRequest $request): array
    {
        return $this->calculatedRelations ??
            (
                $this->calculatedRelations =
                    array_map(function (Relation $relation) {
                        return $relation->fromResource($this);
                    }, $this->relations($request))
            );
    }
}
