<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Engine;

use Lomkit\Rest\Tests\Feature\Extensions\Stubs\RedRowsExtension;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\RestrictionLog;
use Lomkit\Rest\Tests\Feature\Text\Engine\EngineSearchTestCase;
use Lomkit\Rest\Tests\Fixtures\Ledger\Voucher;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
class SqlDriverRestrictionTest extends EngineSearchTestCase
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        RestrictionLog::reset();
        config(['scout.driver' => 'collection', 'rest.extensions' => [RedRowsExtension::class]]);
    }

    #[\Override]
    protected function tearDown(): void
    {
        RestrictionLog::reset();

        parent::tearDown();
    }

    #[Test]
    public function it_restricts_the_rows_and_the_total_on_the_collection_driver(): void
    {
        $response = $this->search('vouchers', ['text' => ['value' => 'report']]);

        $response->assertOk()->assertExactJson($this->page([5, 1, 3], 3));
    }

    #[Test]
    public function it_keeps_the_client_filters_on_the_collection_driver(): void
    {
        $response = $this->search('vouchers', ['text' => ['value' => 'report'], 'filters' => [
            ['field' => 'number', 'operator' => '>', 'value' => 1],
            ['field' => 'code', 'operator' => 'in', 'value' => ['V-a', 'V-c', 'V-d']],
        ]]);

        $response->assertOk()->assertExactJson($this->page([1], 1));
    }

    #[Test]
    public function it_keeps_the_client_exclusions_on_the_collection_driver(): void
    {
        $response = $this->search('vouchers', ['text' => ['value' => 'report'], 'filters' => [
            ['field' => 'code', 'operator' => 'not in', 'value' => ['V-a', 'V-d']],
        ]]);

        $response->assertOk()->assertExactJson($this->page([5, 1], 2));
    }

    #[Test]
    public function it_restricts_the_targets_of_an_action_on_the_collection_driver(): void
    {
        $response = $this->postJson('/api/vouchers/actions/deactivate-vouchers', ['search' => ['text' => ['value' => 'report']]]);

        $response->assertOk()->assertExactJson(['data' => ['impacted' => 3, 'result' => null]]);
        $this->assertSame([1, 3, 5], Voucher::query()->where('active', false)->orderBy('id')->pluck('id')->all());
    }

    #[Test]
    public function it_returns_an_empty_page_on_the_null_driver(): void
    {
        config(['scout.driver' => 'null']);

        $response = $this->search('vouchers', ['text' => ['value' => 'report']]);

        $response->assertOk()->assertExactJson($this->page([], 0));
    }
}
