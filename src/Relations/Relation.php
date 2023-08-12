<?php

namespace Lomkit\Rest\Relations;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Lomkit\Rest\Concerns\Relations\HasPivotFields;
use Lomkit\Rest\Contracts\RelationResource;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Concerns\Makeable;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Relations\Traits\Constrained;
use Lomkit\Rest\Relations\Traits\Mutates;
use Lomkit\Rest\Rules\RequiredRelation;

class Relation
{
    use Makeable, Mutates, Constrained;
    public string $relation;
    protected array $types;

    protected Resource $fromResource;

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

    public function applySearchQuery(Builder $query) {
        $resource = $this->resource();

        $resource->searchQuery(app()->make(RestRequest::class), $query);
    }

    public function hasMultipleEntries() {
        return false;
    }

    public function resource() {
        return new $this->types[0];
    }

    public function fromResource(Resource $fromResource) {
        return tap($this, function () use ($fromResource) {
            $this->fromResource = $fromResource;
        });
    }

    public function rules(Resource $resource, string $prefix) {
        $rules = [];

        if ($this->isRequiredOnCreation(
            app()->make(RestRequest::class)
        )) {
            $rules[$prefix] = RequiredRelation::make()->resource($resource);
        }

        if (in_array(HasPivotFields::class, class_uses_recursive($this), true)) {

            $pivotPrefix = $prefix;
            if ($this->hasMultipleEntries()) {
                $pivotPrefix .= '.*';
            }
            $pivotPrefix.= '.pivot.';
            // @TODO pivot fields here :) + doc
            foreach ($this->getPivotRules() as $pivotKey => $pivotRule) {
                $rules[$pivotPrefix.$pivotKey] = $pivotRule;
            }
        }

        return $rules;
    }
}