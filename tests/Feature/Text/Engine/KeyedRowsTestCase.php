<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Query\EngineSearch;
use Lomkit\Rest\Search\SearchNode;
use Lomkit\Rest\Tests\Fixtures\Ledger\Ledger;

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
}
