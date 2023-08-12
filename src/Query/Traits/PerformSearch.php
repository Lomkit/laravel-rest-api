<?php

namespace Lomkit\Rest\Query\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Tests\Support\Rest\Resources\BelongsToManyResource;

trait PerformSearch
{
    public function search(array $parameters = []) {
        $this->authorizeTo('viewAny', $this->resource::$model);

        $this->resource->searchQuery(app()->make(RestRequest::class), $this->queryBuilder);

        // Here we run the filters in a subquery to avoid conflicts
        $this->when(isset($parameters['filters']), function () use ($parameters) {
            $this->queryBuilder->where(function($query) use ($parameters) {
                $this->newQueryBuilder(['resource' => $this->resource, 'query' => $query])
                    ->applyFilters($parameters['filters']);
            });
        });

        if (!isset($parameters['sorts']) || empty($parameters['sorts'])) {
            foreach ($this->resource->defaultOrderBy(
                app()->make(RestRequest::class)
            ) as $column => $order) {
                $this->queryBuilder->orderBy($this->queryBuilder->getModel()->getTable().'.'.$column, $order);
            }
        }
        $this->when(isset($parameters['sorts']), function () use ($parameters) {
            $this->applySorts($parameters['sorts']);
        });

        $this->when(isset($parameters['scopes']), function () use ($parameters) {
            $this->applyScopes($parameters['scopes']);
        });

        $this->when(isset($parameters['includes']), function () use ($parameters) {
            $this->applyIncludes($parameters['includes']);
        });

        $this->when(isset($parameters['aggregates']), function () use ($parameters) {
            $this->applyAggregates($parameters['aggregates']);
        });

        // @TODO: is this a problem also with HasMany ??
        // @TODO: this will be the problem for every relation, need to fix this
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
                $relation->applySearchQuery($query);
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
            $this->sort($this->queryBuilder->getModel()->getTable().'.'.$sort['field'], $sort['direction'] ?? 'asc');
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

            $queryBuilder = $this->newQueryBuilder(['resource' => $resource, 'query' => $query]);

            return $queryBuilder->search($include);
        });
    }

    public function applyIncludes($includes) {
        foreach ($includes as $include) {
            $this->include($include);
        }
    }

    public function aggregate($aggregate) {
        return $this->queryBuilder->withAggregate([$aggregate['relation'] => function(Builder $query) use ($aggregate) {
            $resource = $this->resource->relationResource($aggregate['relation']);

            $queryBuilder = $this->newQueryBuilder(['resource' => $resource, 'query' => $query]);

            return $queryBuilder->search(['filters' => $aggregate['filters'] ?? []]);
        }], $aggregate['field'] ?? '*', $aggregate['type']);
    }

    public function applyAggregates($aggregates) {
        foreach ($aggregates as $aggregate) {
            $this->aggregate($aggregate);
        }
    }
}