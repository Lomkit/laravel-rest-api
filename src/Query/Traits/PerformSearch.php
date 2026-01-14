<?php

namespace Lomkit\Rest\Query\Traits;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Query\Operators\Aggregate;
use Lomkit\Rest\Query\Operators\Filter;
use Lomkit\Rest\Query\Operators\IncludeOperator;
use Lomkit\Rest\Query\Operators\Instruction;
use Lomkit\Rest\Query\Operators\Scope;
use Lomkit\Rest\Query\Operators\Sort;

trait PerformSearch
{
    /**
     * Executes a modular search on the query builder based on provided parameters.
     *
     * This method first authorizes the view operation on the model and then, unless security is disabled,
     * applies a security-aware search query. It conditionally processes various search parameters to:
     * - Filter results via a nested subquery to prevent conflicts.
     * - Sort results using custom definitions if provided, or default ordering from the resource.
     * - Apply named scopes, instructions, includes, and aggregate functions.
     * - Limit the result set according to a specified limit or a default value of 50.
     *
     * @param array $parameters An associative array of search parameters that may include:
     *                          - 'filters': Criteria for filtering the results.
     *                          - 'sorts': Definitions for ordering the results.
     *                          - 'scopes': Named scopes to refine the query.
     *                          - 'instructions': Additional instructions for query customization.
     *                          - 'includes': Related resources to include in the query.
     *                          - 'aggregates': Aggregate functions to apply on related data.
     *                          - 'limit': Maximum number of records to return.
     *
     * @return Builder The modified query builder with the applied search parameters.
     */
    public function search(array $parameters = []): Builder
    {
        $this->resource->authorizeTo('viewAny', $this->resource::$model);

        $this->when(!$this->disableSecurity, function () {
            $this->resource->searchQuery(app()->make(RestRequest::class), $this->queryBuilder);
        });

        // Here we run the filters in a subquery to avoid conflicts
        $this->when(isset($parameters['filters']), function () use ($parameters) {
            $this->queryBuilder->where(function ($query) use ($parameters) {
                $this->newQueryBuilder(['resource' => $this->resource, 'query' => $query])
                    ->applyFilters($parameters['filters']);
            });
        });

        if (empty($parameters['sorts'])) {
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

        $limit = $this->disableDefaultLimit() && !isset($parameters['limit']) ? null : ($parameters['limit'] ?? 50);
        if ($limit !== null) {
            $this->queryBuilder->limit($limit);
        }

        return $this->queryBuilder;
    }

    /**
     * Apply multiple filters to the query builder.
     *
     * @param array $filters An array of filters to apply.
     */
    public function applyFilters(array $filters): void
    {
        foreach ($filters as $filter) {
            (new Filter(
                $filter['field'] ?? '',
                $filter['operator'] ?? '=',
                $filter['value'] ?? null,
                $filter['type'] ?? 'and',
                $filter['nested'] ?? null
            ))
                ->handle($this->queryBuilder, $this->resource);
        }
    }

    /**
     * Apply multiple sorts to the query builder.
     *
     * @param array $sorts An array of sorts to apply.
     */
    public function applySorts(array $sorts): void
    {
        foreach ($sorts as $sort) {
            (new Sort(
                field: $this->queryBuilder->getModel()->getTable() . '.' . $sort['field'],
                direction: $sort['direction'] ?? 'asc',
            ))
                ->handle($this->queryBuilder, $this->resource);
        }
    }

    /**
     * Apply multiple scopes to the query builder.
     *
     * @param array $scopes An array of scopes to apply.
     */
    public function applyScopes($scopes)
    {
        foreach ($scopes as $scope) {
            (new Scope(
                name: $scope['name'],
                parameters: $scope['parameters'] ?? []
            ))
                ->handle($this->queryBuilder, $this->resource);
        }
    }

    /**
     * Apply multiple instructions to the query builder.
     *
     * @param array $instructions An array of instructions to apply.
     */
    public function applyInstructions(array $instructions): void
    {
        foreach ($instructions as $instruction) {
            (new Instruction(
                name: $instruction['name'],
                fields: $instruction['fields'] ?? []
            ))
                ->handle($this->queryBuilder, $this->resource);
        }
    }

    /**
     * Apply includes to the query builder.
     *
     * @param array $includes An array of relationships to include.
     */
    public function applyIncludes(array $includes): void
    {
        foreach ($includes as $include) {
            (new IncludeOperator(
                $include
            ))
                ->handle($this->queryBuilder, $this->resource);
        }
    }

    /**
     * Apply aggregate functions to the query builder.
     *
     * @param array $aggregates An array of aggregate functions to apply.
     */
    public function applyAggregates(array $aggregates): void
    {
        foreach ($aggregates as $aggregate) {
            (new Aggregate(
                relation: $aggregate['relation'],
                alias: $aggregate['alias'] ?? null,
                type: $aggregate['type'],
                field: $aggregate['field'] ?? '*',
                filters: $aggregate['filters'] ?? []
            ))
                ->handle($this->queryBuilder, $this->resource);
        }
    }

    // @TODO: add select part ?
}
