<?php

namespace Lomkit\Rest\Query\Traits;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Tests\Support\Rest\Resources\BelongsToManyResource;

trait PerformSearch
{
    /**
     * Perform a search operation on the query builder.
     *
     * @param array $parameters An array of search parameters.
     * @return Builder The modified query builder.
     */
    public function search(array $parameters = []) {
        $this->resource->authorizeTo('viewAny', $this->resource::$model);

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

        $this->when(isset($parameters['instructions']), function () use ($parameters) {
            $this->applyInstructions($parameters['instructions']);
        });

        $this->when(isset($parameters['includes']), function () use ($parameters) {
            $this->applyIncludes($parameters['includes']);
        });

        $this->when(isset($parameters['aggregates']), function () use ($parameters) {
            $this->applyAggregates($parameters['aggregates']);
        });

        // In case we are in a relation we don't apply the limits since we dont know how much records will be related.
        if (!$this->queryBuilder instanceof Relation) {
            $this->queryBuilder->limit($parameters['limit'] ?? 50);
        }

        return $this->queryBuilder;
    }

    /**
     * Apply a filter to the query builder.
     *
     * @param string $field The field to filter on.
     * @param string $operator The filter operator.
     * @param mixed $value The filter value.
     * @param string $type The filter type (e.g., 'and' or 'or').
     * @param array|null $nested Nested filters.
     */
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

    /**
     * Apply multiple filters to the query builder.
     *
     * @param array $filters An array of filters to apply.
     */
    public function applyFilters($filters) {
        foreach ($filters as $filter) {
            $this->filter($filter['field'] ?? null, $filter['operator'] ?? '=', $filter['value'] ?? null, $filter['type'] ?? 'and', $filter['nested'] ?? null);
        }
    }

    /**
     * Sort the query builder by a field and direction.
     *
     * @param string $field The field to sort by.
     * @param string $direction The sort direction ('asc' or 'desc').
     */
    public function sort($field, $direction = 'asc') {
        return $this->queryBuilder->orderBy($field, $direction);
    }

    /**
     * Apply multiple sorts to the query builder.
     *
     * @param array $sorts An array of sorts to apply.
     */
    public function applySorts($sorts) {
        foreach ($sorts as $sort) {
            $this->sort($this->queryBuilder->getModel()->getTable().'.'.$sort['field'], $sort['direction'] ?? 'asc');
        }
    }

    /**
     * Apply a scope to the query builder.
     *
     * @param string $name The name of the scope.
     * @param array $parameters The scope parameters.
     */
    public function scope($name, $parameters = []) {
        return $this->queryBuilder->{$name}(...$parameters);
    }

    /**
     * Apply multiple scopes to the query builder.
     *
     * @param array $scopes An array of scopes to apply.
     */
    public function applyScopes($scopes) {
        foreach ($scopes as $scope) {
            $this->scope($scope['name'], $scope['parameters'] ?? []);
        }
    }

    /**
     * Apply an instruction to the query builder.
     *
     * @param string $name The name of the instruction.
     * @param array $fields The instruction fields.
     */
    public function instruction($name, $fields = []) {
        return $this->resource->instruction(app(RestRequest::class), $name)
            ->handle(
                collect($fields)->mapWithKeys(function ($field) {return [ $field['name'] => $field['value']]; })->toArray(),
                $this->queryBuilder
            );
    }

    /**
     * Apply multiple instructions to the query builder.
     *
     * @param array $instructions An array of instructions to apply.
     */
    public function applyInstructions($instructions) {
        foreach ($instructions as $instruction) {
            $this->instruction($instruction['name'], $instruction['fields'] ?? []);
        }
    }

    /**
     * Include related resources in the query.
     *
     * @param array $include An array of relationships to include.
     */
    public function include($include) {
        return $this->queryBuilder->with($include['relation'], function(Relation $query) use ($include) {
            $resource = $this->resource->relationResource($include['relation']);

            $queryBuilder = $this->newQueryBuilder(['resource' => $resource, 'query' => $query]);

            return $queryBuilder->search($include);
        });
    }

    /**
     * Apply includes to the query builder.
     *
     * @param array $includes An array of relationships to include.
     */
    public function applyIncludes($includes) {
        foreach ($includes as $include) {
            $this->include($include);
        }
    }

    /**
     * Apply an aggregate function to the query builder.
     *
     * @param array $aggregate An array defining the aggregate function.
     */
    public function aggregate($aggregate) {
        return $this->queryBuilder->withAggregate([$aggregate['relation'] => function(Builder $query) use ($aggregate) {
            $resource = $this->resource->relationResource($aggregate['relation']);

            $queryBuilder = $this->newQueryBuilder(['resource' => $resource, 'query' => $query]);

            return $queryBuilder->search(['filters' => $aggregate['filters'] ?? []]);
        }], $aggregate['field'] ?? '*', $aggregate['type']);
    }

    /**
     * Apply aggregate functions to the query builder.
     *
     * @param array $aggregates An array of aggregate functions to apply.
     */
    public function applyAggregates($aggregates) {
        foreach ($aggregates as $aggregate) {
            $this->aggregate($aggregate);
        }
    }
}