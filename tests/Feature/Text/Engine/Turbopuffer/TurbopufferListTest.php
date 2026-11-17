<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine\Turbopuffer;

use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Lomkit\Rest\Actions\QueuedAction;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Concerns\ResetsExtensionStubs;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\RedEverywhereExtension;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\KeylessListedVoucherController;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\ListedVoucherController;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\TiedVoucherController;
use Lomkit\Rest\Tests\Fixtures\Ledger\Voucher;
use Lomkit\Rest\Tests\Fixtures\ReceivedFields;
use Orchestra\Testbench\Attributes\WithConfig;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
#[Group('turbopuffer')]
class TurbopufferListTest extends TurbopufferTestCase
{
    use ResetsExtensionStubs;

    private const array FILTERS = ['And', [['__soft_deleted', 'Eq', 0], ['price', 'Lt', 40]]];

    #[\Override]
    protected function defineRoutes($router): void
    {
        parent::defineRoutes($router);

        Route::prefix('api')->group(function (): void {
            Rest::resource('listed-vouchers', ListedVoucherController::class)->withSoftDeletes();
            Rest::resource('tied-vouchers', TiedVoucherController::class)->withSoftDeletes();
            Rest::resource('keyless-listed-vouchers', KeylessListedVoucherController::class)->withSoftDeletes();
        });
    }

    #[Test]
    public function it_lists_without_a_text_term_in_key_order_with_the_total_the_engine_counts(): void
    {
        $this->rows(['V-a', 'V-b', 'V-c'], 4);

        $response = $this->search('listed-vouchers', ['filters' => [['field' => 'price', 'operator' => '<', 'value' => 40]], 'limit' => 2]);

        $response->assertOk()->assertExactJson($this->page([3, 2], 4, 2));
        $this->assertSame([
            ['rank_by' => ['id', 'desc'], 'filters' => self::FILTERS, 'limit' => 2],
            ['aggregate_by' => ['count' => ['Count']], 'filters' => self::FILTERS],
        ], $this->queries());
    }

    #[Test]
    public function it_ranks_a_list_by_the_one_sort_the_engine_runs_without_text(): void
    {
        $this->rows(['V-e', 'V-b'], 5);

        $response = $this->search('listed-vouchers', ['sorts' => [['field' => 'price', 'direction' => 'desc']], 'limit' => 2]);

        $response->assertOk()->assertExactJson($this->page([5, 2], 5, 2));
        $this->assertSame(['price', 'desc'], $this->queries()[0]['rank_by']);
    }

    #[Test]
    public function it_refuses_a_second_sort_without_text_and_any_sort_with_text(): void
    {
        $list = $this->search('listed-vouchers', ['sorts' => [['field' => 'price'], ['field' => 'number']]]);
        $text = $this->search('listed-vouchers', ['text' => ['value' => 'report'], 'sorts' => [['field' => 'price']]]);

        $list->assertUnprocessable()->assertJsonPath('errors', ['search.sorts' => ['The search.sorts field must not have more than 1 items.']]);
        $text->assertUnprocessable()->assertJsonPath('errors', ['search.sorts.0.field' => ['The selected search.sorts.0.field is invalid.']]);
        $this->assertSame([], $this->queries());
    }

    #[Test]
    #[WithConfig('rest.extensions', [RedEverywhereExtension::class])]
    public function it_restricts_a_list_on_the_engine_and_shows_every_row_that_the_engine_returns(): void
    {
        $this->rows(['V-d', 'V-a'], 2);

        $response = $this->search('listed-vouchers', []);

        $response->assertOk()->assertExactJson($this->page([4, 3], 2));
        $this->assertSame(['And', [['__soft_deleted', 'Eq', 0], ['team', 'In', ['red']]]], $this->queries()[0]['filters']);
    }

    #[Test]
    #[WithConfig('rest.extensions', [RedEverywhereExtension::class])]
    public function it_runs_an_action_on_the_engine_keys_of_a_list_read_again_through_the_restrictions(): void
    {
        $received = $this->app->instance(ReceivedFields::class, new ReceivedFields);
        $this->rows(['V-d', 'V-a', 'V-b'], 3);

        $response = $this->postJson('/api/listed-vouchers/actions/deactivate-vouchers', ['search' => ['filters' => [['field' => 'price', 'operator' => '<', 'value' => 40]]]]);

        $response->assertOk()->assertExactJson(['data' => ['impacted' => 2, 'result' => null]]);
        $this->assertSame([['handle', ['ids' => [3, 2]]]], $received->calls());
        $this->assertSame(['rank_by' => ['id', 'desc'], 'filters' => ['And', [['__soft_deleted', 'Eq', 0], ['price', 'Lt', 40], ['team', 'In', ['red']]]]], array_diff_key($this->queries()[0], ['limit' => true]));
    }

    #[Test]
    #[WithConfig('rest.extensions', [RedEverywhereExtension::class])]
    public function it_reads_the_keys_of_a_queued_list_action_again_through_the_restrictions_in_the_worker(): void
    {
        Queue::fake();
        $received = $this->app->instance(ReceivedFields::class, new ReceivedFields);
        $this->rows(['V-a', 'V-b'], 2);

        $this->postJson('/api/listed-vouchers/actions/queued-deactivate-vouchers', ['search' => []])->assertOk();
        Voucher::query()->whereKey(2)->update(['team' => 'blue']);
        $jobs = Queue::pushed(QueuedAction::class);
        $this->app->call([$jobs->sole(), 'handle']);

        $this->assertSame([3, 2], $jobs->sole()->rows->keys);
        $this->assertSame([['handle', ['ids' => [3]]]], $received->calls());
    }

    #[Test]
    public function it_ranks_a_list_sent_without_a_sort_on_the_tie_breaker_or_the_key_descending(): void
    {
        $this->rows(['V-e', 'V-d'], 5);

        $tied = $this->search('tied-vouchers', ['limit' => 2]);
        $keyless = $this->search('keyless-listed-vouchers', ['limit' => 10]);

        $tied->assertOk()->assertExactJson($this->page([5, 4], 5, 2));
        $keyless->assertOk()->assertJsonPath('data.*.id', [5, 4]);
        $this->assertSame([['title', 'desc'], ['id', 'desc']], [$this->queries()[0]['rank_by'], $this->queries()[2]['rank_by']]);
    }

    #[Test]
    public function it_ranks_a_list_by_the_requested_sort_alone_since_the_engine_takes_one(): void
    {
        $this->rows(['V-e', 'V-b'], 5);

        $this->search('tied-vouchers', ['sorts' => [['field' => 'price', 'direction' => 'desc']], 'limit' => 2])->assertOk();

        $this->assertSame(['price', 'desc'], $this->queries()[0]['rank_by']);
    }
}
