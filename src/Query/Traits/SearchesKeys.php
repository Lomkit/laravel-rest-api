<?php

namespace Lomkit\Rest\Query\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Authorization\Check;
use Lomkit\Rest\Exceptions\InvalidResourceException;
use Lomkit\Rest\Query\Clauses\PerimeterClause;
use Lomkit\Rest\Query\Clauses\TrashedClause;
use Lomkit\Rest\Text\Engine\EngineKeys;
use Lomkit\Rest\Text\Engine\ScoutModel;

trait SearchesKeys
{
    /**
     * @param  list<int|string>  $keys
     * @param  array<string, mixed>  $parameters
     * @return Builder<Model>
     */
    public function searchKeys(array $keys, array $parameters = []): Builder
    {
        $this->resource
            ->context()
            ->authorizer()
            ->ensurePlanned(Check::onClass($this->resource, 'viewAny'));

        $model = $this->resource::newModel();
        $scout = ScoutModel::tryFrom($model) ?? throw InvalidResourceException::unsearchableModel($model::class);
        $query = $this->queryBuilder = $model->newQuery();

        (new PerimeterClause($this->resource))->apply($query);
        EngineKeys::of($scout, $keys)->constrain($query);
        TrashedClause::fromPayload($parameters['trashed'] ?? null)?->apply($query);

        if (is_array($parameters['includes'] ?? null)) {
            $this->applyIncludes($parameters['includes']);
        }

        if (is_array($parameters['aggregates'] ?? null)) {
            $this->applyAggregates($parameters['aggregates']);
        }

        return $query;
    }
}
