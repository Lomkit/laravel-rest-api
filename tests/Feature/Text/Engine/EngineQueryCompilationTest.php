<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine;

use Illuminate\Support\Facades\DB;
use Laravel\Scout\Engines\NullEngine;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\CallbackVoucher;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\RecordingCollectionEngine;
use Lomkit\Rest\Tests\Fixtures\Ledger\VoucherResource;
use Lomkit\Rest\Text\Engine\EnginePage;
use Lomkit\Rest\Text\Engine\EngineQuery;
use Lomkit\Rest\Text\TextQuery;
use Lomkit\Rest\Text\TextTarget;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
class EngineQueryCompilationTest extends EngineQueryTestCase
{
    #[Test]
    public function it_returns_an_empty_page_without_searching_when_the_in_lists_share_no_value(): void
    {
        $engine = new RecordingCollectionEngine;
        $this->useEngine($engine);
        DB::enableQueryLog();

        $page = $this->search($this->engine(fn (EngineQuery $query): EngineQuery => $query->whereIn('number', [1, 3])))
            ->whereIn('number', [5])
            ->page(10, 1);

        $this->assertEquals(EnginePage::empty(), $page);
        $this->assertSame([], $engine->searches);
        $this->assertSame([], DB::getQueryLog());
    }

    #[Test]
    public function it_returns_an_empty_page_without_searching_for_an_empty_in_list(): void
    {
        $engine = new RecordingCollectionEngine;
        $this->useEngine($engine);

        $page = $this->search($this->engine(fn (EngineQuery $query): EngineQuery => $query->whereIn('team', [])))->page(10, 1);

        $this->assertEquals(EnginePage::empty(), $page);
        $this->assertSame([], $engine->searches);
    }

    #[Test]
    public function it_compiles_its_conditions_into_one_plain_scout_builder(): void
    {
        $engine = new RecordingCollectionEngine;
        $this->useEngine($engine);

        $this->search($this->engine(fn (EngineQuery $query): EngineQuery => $query->whereIn('number', [1, 3, 5])->whereNotIn('team', ['blue'])))
            ->where('price', '<', '40')
            ->whereIn('number', ['5', '1'])
            ->whereNotIn('team', ['green', 'blue'])
            ->orderBy('price', 'desc')
            ->page(10, 1);

        $builder = $engine->searches[0];
        $this->assertSame('report', $builder->query);
        $this->assertSame([['field' => 'price', 'operator' => '<', 'value' => 40]], $builder->wheres);
        $this->assertSame(['number' => [1, 5]], $builder->whereIns);
        $this->assertSame(['team' => ['blue', 'green']], $builder->whereNotIns);
        $this->assertSame([['column' => 'price', 'direction' => 'desc']], $builder->orders);
        $this->assertSame([], $builder->options);
        $this->assertNull($builder->callback);
        $this->assertNull($builder->queryCallback);
    }

    #[Test]
    public function it_runs_without_the_search_override_of_the_model(): void
    {
        $resource = $this->resource(VoucherResource::class);
        $fields = $resource->getFields($resource->context()->request);
        $target = new TextTarget(VoucherResource::class, $fields, new CallbackVoucher, $fields);

        $page = $this->engine()
            ->query(new TextQuery('alpha'), $target, $resource->context()->request)
            ->page(10, 1);

        $this->assertEquals(new EnginePage(['V-c'], 1), $page);
    }

    #[Test]
    public function it_returns_an_empty_page_from_the_null_engine_without_searching(): void
    {
        $this->useEngine(new NullEngine);
        DB::enableQueryLog();

        $page = $this->search()->page(10, 1);

        $this->assertEquals(EnginePage::empty(), $page);
        $this->assertSame([], DB::getQueryLog());
    }

    #[Test]
    public function it_runs_on_a_subclass_of_a_scout_engine_with_the_rules_of_its_parent(): void
    {
        $engine = new RecordingCollectionEngine;
        $this->useEngine($engine);

        $page = $this->search()
            ->orderBy('number', 'asc')
            ->page(10, 1);

        $this->assertEquals(new EnginePage(['V-a', 'V-c', 'V-d', 'V-e', 'V-g'], 5), $page);
        $this->assertCount(1, $engine->searches);
    }
}
