<?php

namespace Lomkit\Rest\Relations;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Lomkit\Rest\Contracts\RelationResource;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Concerns\Makeable;
use Lomkit\Rest\Http\Resource;

class Relation
{
    use Makeable;
    public string $relation;
    protected array $types;

    protected Resource $fromResource;

    //@TODO toutes les méthodes: "requiredOnCreate", ...

    public function __construct($relation, $type)
    {
        $this->relation = $relation;
        $this->types = [$type];
    }

    public function filter(Builder $query, $relation, $operator, $value, $boolean = 'and', Closure $callback = null)
    {
        return $query->has(Str::beforeLast(relation_without_pivot($relation), '.'), '>=', 1, $boolean, function (Builder $query) use ($value, $operator, $relation, $callback) {

            $field = (Str::contains($relation, '.pivot.') ?
                    $this->fromResource::newModel()->{Str::of($relation)->before('.pivot.')->afterLast('.')->toString()}()->getTable() :
                    $query->getModel()->getTable()).'.'.Str::afterLast($relation, '.');

            if (in_array($operator, ['in', 'not in'])) {
                $query->whereIn($field, $value, 'and', $operator === 'not in');
            } else {
                $query->where($field, $operator, $value);
            }

            $callback($query);
        });
    }

    public function applyFetchQuery(Builder $query) {
        //@TODO: apply custom fetchQuery when needed
        //@T0DO: don't apply fetchQuery on polymorphic relation ?
        $resource = $this->resource();

        $resource->fetchQuery(app()->make(RestRequest::class), $query);
    }

    public function resource() {
        return new $this->types[0];
    }

    public function fromResource(Resource $fromResource) {
        return tap($this, function () use ($fromResource) {
            $this->fromResource = $fromResource;
        });
    }
}