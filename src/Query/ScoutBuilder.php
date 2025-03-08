<?php

namespace Lomkit\Rest\Query;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Traits\Conditionable;
use Lomkit\Rest\Contracts\QueryBuilder;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;

class ScoutBuilder implements QueryBuilder
{
    use Conditionable;

    /**
     * Executes a search query using Laravel Scout with customizable criteria.
     *
     * This method configures the underlying search builder by setting the query text,
     * and conditionally applying filters, sort orders, and additional instructions based
     * on the provided parameters. It also enforces a limit on the number of results,
     * defaulting to 50 if no limit is specified. Any extra parameters, after excluding
     * reserved keys (filters, instructions, sorts, text, and limit), are forwarded to the
     * underlying search operation.
     *
     * @param array $parameters An associative array of search criteria, which may include:
     *                          - 'text': The search query string.
     *                          - 'filters': An array of filter conditions.
     *                          - 'sorts': An array of sorting directives.
     *                          - 'instructions': Additional query instructions.
     *                          - 'limit': Maximum number of results to return (defaults to 50 if not provided).
     *
     * @return \Laravel\Scout\Builder The configured Scout query builder.
     */
    public function search(array $parameters = [])
    {
        $this->resource->searchScoutQuery(app()->make(RestRequest::class), $this->queryBuilder);

        $this->queryBuilder->query = $parameters['text']['value'] ?? '';

        $this->when(isset($parameters['filters']), function () use ($parameters) {
            $this->applyFilters($parameters['filters']);
        });

        $this->when(isset($parameters['sorts']), function () use ($parameters) {
            $this->applySorts($parameters['sorts']);
        });

        $this->when(isset($parameters['instructions']), function () use ($parameters) {
            $this->applyInstructions($parameters['instructions']);
        });

        $this->queryBuilder->take($parameters['limit'] ?? 50);

        $this->queryBuilder
            ->query(function (Builder $query) use ($parameters) {
                app()->make(QueryBuilder::class, ['query' => $query, 'resource' => $this->resource])
                    ->disableSecurity()
                    ->search(
                        collect($parameters)
                            ->except([
                                'filters',
                                'instructions',
                                'sorts',
                                'text',
                                'limit',
                            ])
                            ->all()
                    );
            });

        return $this->queryBuilder;
    }

    /**
     * Apply multiple filters to the query builder.
     *
     * @param array $filters An array of filters to apply.
     */
    public function applyFilters($filters)
    {
        foreach ($filters as $filter) {
            $this->filter($filter['field'] ?? null, $filter['operator'] ?? '=', $filter['value'] ?? null, $filter['type'] ?? 'and');
        }
    }

    /**
     * Apply a filter to the query builder.
     *
     * @param string $field    The field to filter on.
     * @param string $operator The filter operator.
     * @param mixed  $value    The filter value.
     */
    public function filter($field, $operator, $value)
    {
        if ($operator === 'in') {
            $this->queryBuilder->whereIn($field, $value);
        } elseif ($operator === 'not in') {
            $this->queryBuilder->whereNotIn($field, $value);
        } else {
            $this->queryBuilder->where($field, $value);
        }
    }

    /**
     * Sort the query builder by a field and direction.
     *
     * @param string $field     The field to sort by.
     * @param string $direction The sort direction ('asc' or 'desc').
     */
    public function sort($field, $direction = 'asc')
    {
        return $this->queryBuilder->orderBy($field, $direction);
    }

    /**
     * Apply multiple sorts to the query builder.
     *
     * @param array $sorts An array of sorts to apply.
     */
    public function applySorts($sorts)
    {
        foreach ($sorts as $sort) {
            $this->sort($sort['field'], $sort['direction'] ?? 'asc');
        }
    }

    /**
     * Apply an instruction to the query builder.
     *
     * @param string $name   The name of the instruction.
     * @param array  $fields The instruction fields.
     */
    public function instruction($name, $fields = [])
    {
        $this->resource->scoutInstruction(app(RestRequest::class), $name)
            ->handleScout(
                collect($fields)->mapWithKeys(function ($field) {return [$field['name'] => $field['value']]; })->toArray(),
                $this->queryBuilder
            );
    }

    /**
     * Apply multiple instructions to the query builder.
     *
     * @param array $instructions An array of instructions to apply.
     */
    public function applyInstructions($instructions)
    {
        foreach ($instructions as $instruction) {
            $this->instruction($instruction['name'], $instruction['fields'] ?? []);
        }
    }

    /**
     * Construct a new query builder for a resource.
     *
     * @param resource $resource
     *
     * @return void
     */
    public function __construct(Resource $resource)
    {
        $this->resource = $resource;
        $this->queryBuilder = $resource::newModel()::search();
    }

    /**
     * The query builder instance.
     *
     * @var resource
     */
    protected $resource;

    /**
     * The query builder instance.
     *
     * @var \Laravel\Scout\Builder
     */
    protected $queryBuilder;

    /**
     * Convert the query builder to an Eloquent query builder.
     *
     * @return \Laravel\Scout\Builder
     */
    public function toBase()
    {
        return $this->queryBuilder;
    }
}
