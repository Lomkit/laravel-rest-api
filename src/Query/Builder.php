<?php

namespace Lomkit\Rest\Query;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;
use Illuminate\Support\Traits\Conditionable;
use Illuminate\Support\Traits\Tappable;
use Lomkit\Rest\Contracts\QueryBuilder;
use Lomkit\Rest\Http\Controllers\Controller;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Relations\BelongsTo;
use Lomkit\Rest\Relations\BelongsToMany;
use Lomkit\Rest\Relations\HasMany;
use Lomkit\Rest\Relations\HasOne;
use RuntimeException;

class Builder implements QueryBuilder
{
    use Tappable,
        Conditionable;

    /**
     * Construct a new query builder for a resource.
     *
     * @param  Resource  $resource
     * @return void
     */
    public function __construct(Resource $resource, \Illuminate\Database\Eloquent\Builder|Relation $query = null)
    {
        $this->resource = $resource;
        $this->queryBuilder = $query ?? $resource::newModel()->query();
    }

    /**
     * The query builder instance.
     *
     * @var Resource
     */
    protected $resource;

    /**
     * The query builder instance.
     *
     * @var \Illuminate\Database\Eloquent\Builder|null
     */
    protected $queryBuilder;

    public function newQueryBuilder($parameters) {
        return app()->make(QueryBuilder::class, $parameters);
    }

    /**
     * Convert the query builder to an Eloquent query builder.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function toBase()
    {
        return $this->queryBuilder;
    }

    public function search(array $parameters = []) {
        // Here we run the filters in a subquery to avoid conflicts
        $this->when(isset($parameters['filters']), function () use ($parameters) {
            $this->queryBuilder->where(function($query) use ($parameters) {
                $this->newQueryBuilder(['resource' => $this->resource, 'query' => $query])
                    ->applyFilters($parameters['filters']);
            });
        });

        $this->when(isset($parameters['sorts']), function () use ($parameters) {
            $this->applySorts($parameters['sorts']);
        });

        $this->when(isset($parameters['scopes']), function () use ($parameters) {
            $this->applyScopes($parameters['scopes']);
        });

        $this->when(isset($parameters['includes']), function () use ($parameters) {
            $this->applyIncludes($parameters['includes']);
        });

        // @TODO: is this a problem also with HasMany ??
        // In case of BelongsToMany we cap the limit
        $limit = $this->queryBuilder instanceof \Illuminate\Database\Eloquent\Relations\BelongsToMany ? 9999 : ($parameters['limit'] ?? 50);
        $this->queryBuilder->limit($limit);

        return $this->queryBuilder;
    }

    public function filter($field, $operator, $value, $type = 'and', $nested = null) {
        if ($nested !== null) {
            return $this->queryBuilder->where(function ($query) use ($nested) {
                $this->newQueryBuilder(['resource' => $this->resource, 'query' => $query])
                    ->applyFilters($nested);
            }, null, null, $type);
        }

        // Here we assume the user has asked a relation filter
        if (Str::contains($field, '.')) {
            $relation = $this->resource->relation($field);
            return $relation->filter($this->queryBuilder, $field, $operator, $value, $type, function ($query) use ($relation) {
                $relation->applyFetchQuery($query);
            }, $this->resource);
        } else {
            if (in_array($operator, ['in', 'not in'])) {
                $this->queryBuilder->whereIn($this->queryBuilder->getModel()->getTable().'.'.$field, $value, $type, $operator === 'not in');
            } else {
                $this->queryBuilder->where($this->queryBuilder->getModel()->getTable().'.'.$field, $operator, $value, $type);
            }
        }
    }

    public function applyFilters($filters) {
        foreach ($filters as $filter) {
            $this->filter($filter['field'] ?? null, $filter['operator'] ?? '=', $filter['value'] ?? null, $filter['type'] ?? 'and', $filter['nested'] ?? null);
        }
    }

    public function sort($field, $direction = 'asc') {
        return $this->queryBuilder->orderBy($field, $direction);
    }

    public function applySorts($sorts) {
        foreach ($sorts as $sort) {
            $this->sort($sort['field'], $sort['direction'] ?? 'asc');
        }
    }

    public function scope($name, $parameters = []) {
        return $this->queryBuilder->{$name}(...$parameters);
    }

    public function applyScopes($scopes) {
        foreach ($scopes as $scope) {
            $this->scope($scope['name'], $scope['parameters'] ?? []);
        }
    }

    public function include($include) {
        return $this->queryBuilder->with($include['relation'], function(Relation $query) use ($include) {
            $resource = $this->resource->relationResource($include['relation']);

            $resource->fetchQuery(app()->make(RestRequest::class), $query->getQuery());

            $queryBuilder = $this->newQueryBuilder(['resource' => $resource, 'query' => $query]);

            return $queryBuilder->search($include);
        });
    }

    public function applyIncludes($includes) {
        foreach ($includes as $include) {
            $this->include($include);
        }
    }
}