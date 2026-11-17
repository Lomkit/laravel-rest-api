<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use LogicException;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Query\EngineSearch;
use Lomkit\Rest\Search\Plan\Columns\ColumnSelection;
use Lomkit\Rest\Search\Plan\KeyedRows;
use Lomkit\Rest\Search\Plan\LimitPolicy;
use Lomkit\Rest\Search\Plan\SearchPlanner;
use Lomkit\Rest\Search\SearchNode;
use Lomkit\Rest\Tests\Fixtures\Ledger\Ledger;
use Lomkit\Rest\Text\Engine\EngineKeys;
use Lomkit\Rest\Text\Engine\ScoutModel;

abstract class KeyedRowsTestCase extends EngineTestCase
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Ledger::seed();
    }

    /**
     * @param  list<mixed>  $keys
     * @return Collection<int, Model>
     */
    protected function rows(Resource $resource, array $keys, ?SearchNode $search = null): Collection
    {
        return (new EngineSearch($resource, $resource->context()->text($resource), $search ?? SearchNode::empty()))->rows($keys);
    }

    /**
     * @param  list<mixed>  $keys
     * @return Collection<int, Model>
     */
    protected function pageRows(Resource $resource, array $keys, ?SearchNode $search = null): Collection
    {
        $model = ScoutModel::tryFrom($resource::newModel()) ?? throw new LogicException($resource::class.' is indexed.');
        $planner = new SearchPlanner(new KeyedRows(EngineKeys::trusted($model, $keys)), ColumnSelection::of($resource), true);

        return $planner->plan($resource, $search ?? SearchNode::empty(), LimitPolicy::none())
            ->query()
            ->get();
    }
}
