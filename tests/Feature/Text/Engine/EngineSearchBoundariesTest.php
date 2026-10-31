<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine;

use Lomkit\Rest\Exceptions\InvalidTextSearchException;
use Lomkit\Rest\Query\Builder;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\RecordingCollectionEngine;
use Lomkit\Rest\Tests\Fixtures\Ledger\Voucher;
use Lomkit\Rest\Tests\Fixtures\Ledger\VoucherResource;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
class EngineSearchBoundariesTest extends EngineSearchTestCase
{
    private RecordingCollectionEngine $engine;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->engine = new RecordingCollectionEngine;
        $this->useEngine($this->engine);
    }

    #[Test]
    public function it_runs_an_action_with_a_blank_text_on_the_database_search_of_a_resource_searched_on_an_engine(): void
    {
        $response = $this->postJson('/api/vouchers/actions/deactivate-vouchers', ['search' => ['text' => ['value' => ' ']]]);

        $response->assertOk()->assertExactJson(['data' => ['impacted' => 4]]);
        $this->assertSame([1, 2, 3, 5], $this->inactive());
        $this->assertSame([], $this->engine->searches);
    }

    #[Test]
    public function it_runs_an_action_of_a_resource_searched_on_an_engine_on_its_database_search(): void
    {
        $response = $this->postJson('/api/vouchers/actions/deactivate-vouchers', ['search' => ['filters' => [['field' => 'title', 'operator' => 'like', 'value' => 'Report%']]]]);

        $response->assertOk()->assertExactJson(['data' => ['impacted' => 3]]);
        $this->assertSame([1, 3, 5], $this->inactive());
    }

    #[Test]
    public function it_refuses_to_run_an_engine_text_search_as_a_database_query(): void
    {
        $context = $this->context(['search' => ['text' => ['value' => 'report']]]);
        $resource = $this->resource(VoucherResource::class, $context);

        $this->expectExceptionObject(InvalidTextSearchException::runsOnEngine(VoucherResource::class));

        (new Builder($resource))->search(['text' => ['value' => 'report']]);
    }

    /** @return list<int> */
    private function inactive(): array
    {
        return Voucher::withTrashed()
            ->where('active', false)
            ->orderBy('id')
            ->pluck('id')
            ->all();
    }
}
