<?php

namespace Lomkit\Rest\Concerns\Resource;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Lomkit\Rest\Contracts\PolymorphicRelation;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Relations\Relation;

trait Relationable
{
    public function relation(string $name): ?Relation
    {
        $isSubRelation = Str::contains($name, '.');
        $relationName = $isSubRelation ? Str::before($name, '.') : $name;

        $relation = Arr::first($this->getRelations(), function ($relation) use ($relationName) {
            return $relation->relation === $relationName;
        });

        if (! $isSubRelation) {
            return $relation;
        }

        if ($relation === null || ($relation instanceof PolymorphicRelation && $relation->targets()->isPolymorphic())) {
            return null;
        }

        return $relation->resource()->relation(Str::after($name, '.'));
    }

    /** @return array<int, Relation> */
    public function relations(RestRequest $request): array
    {
        return [];
    }

    /** @return array<int, Relation> */
    public function getRelations(): array
    {
        return $this->context()->definition($this)->relations();
    }
}
