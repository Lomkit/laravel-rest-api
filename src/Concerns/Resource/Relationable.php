<?php

namespace Lomkit\Rest\Concerns\Resource;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Relations\Relation;

trait Relationable
{
    /**
     * Get a relation by name.
     *
     * @param string $name
     *
     * @return Relation|null
     */
    public function relation($name)
    {
        $name = relation_without_pivot($name);

        $isSubRelation = Str::contains($name, '.');
        $relationName = $isSubRelation ? Str::before($name, '.') : $name;

        $relation = Arr::first($this->getRelations(app()->make(RestRequest::class)), function ($relation) use ($relationName) {
            return $relation->relation === $relationName;
        });

        if ($isSubRelation) {
            $nestedRelation = Str::after($name, '.');

            return $relation->resource()->relation($nestedRelation);
        }

        return $relation;
    }

    /**
     * The calculated relations if already done in this request.
     *
     * @var array
     */
    protected array $calculatedRelations;

    /**
     * The relations that could be provided.
     *
     * @param RestRequest $request
     *
     * @return array
     */
    public function relations(RestRequest $request): array
    {
        return [];
    }

    /**
     * Get the relations for the resource.
     *
     * @param RestRequest $request
     *
     * @return array
     */
    public function getRelations(RestRequest $request)
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
