<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine\Turbopuffer;

use Lomkit\Rest\Tests\Fixtures\ReceivedFields;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
#[Group('turbopuffer')]
class TurbopufferSearchTest extends TurbopufferTestCase
{
    private const array FILTERS = ['And', [['__soft_deleted', 'Eq', 0], ['price', 'Lt', 40]]];

    #[Test]
    public function it_sends_structured_filters_and_returns_the_total_the_engine_counts(): void
    {
        $this->rows(['V-d', 'V-c', 'V-a'], 4);

        $response = $this->search('unsorted-vouchers', ['text' => ['value' => 'report'], 'filters' => [['field' => 'price', 'operator' => '<', 'value' => 40]], 'limit' => 2]);

        $response->assertOk()->assertExactJson($this->page([1], 4, 2));
        $this->assertSame([
            ['rank_by' => ['title', 'BM25', 'report'], 'filters' => self::FILTERS, 'limit' => 2],
            ['aggregate_by' => ['count' => ['Count']], 'filters' => self::FILTERS],
        ], $this->queries());
    }

    #[Test]
    public function it_reads_the_keys_of_an_action_until_the_engine_returns_a_short_page(): void
    {
        $received = $this->app->instance(ReceivedFields::class, new ReceivedFields);
        $this->rows(['V-e', 'V-d', 'V-c', 'V-a'], 9);

        $response = $this->postJson('/api/unsorted-vouchers/actions/retitle-vouchers', ['search' => ['text' => ['value' => 'report']]]);

        $response->assertOk()->assertExactJson(['data' => ['impacted' => 3]]);
        $this->assertSame([['handle', ['ids' => [5]]], ['handle', ['ids' => [1, 3]]]], $received->calls());
        $this->assertSame([2, 4, 6], array_values(array_filter(array_column($this->queries(), 'limit'))));
    }

    #[Test]
    public function it_refuses_a_page_past_the_window_of_the_engine_before_searching(): void
    {
        $response = $this->search('unsorted-vouchers', ['text' => ['value' => 'report'], 'limit' => 300, 'page' => 34]);

        $response->assertUnprocessable()->assertExactJson([
            'message' => 'The search.page field must not be greater than 33.',
            'errors' => ['search.page' => ['The search.page field must not be greater than 33.']],
        ]);
        $this->assertSame([], $this->queries());
    }
}
