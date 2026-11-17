<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Restrictions;

use Illuminate\Queue\Jobs\SyncJob;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Lomkit\Rest\Actions\QueuedAction;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Concerns\CapturesQueries;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\ForeignEngineProfiles;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\QueuedVoucherController;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\RedEverywhereExtension;
use Lomkit\Rest\Tests\Feature\Text\Engine\EngineSearchTestCase;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\ForeignEngine;
use Lomkit\Rest\Tests\Fixtures\Ledger\Voucher;
use Lomkit\Rest\Tests\Fixtures\Ledger\VoucherResource;
use Lomkit\Rest\Tests\Fixtures\ReceivedFields;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
class StaleEngineRowsTest extends EngineSearchTestCase
{
    use CapturesQueries;

    private ReceivedFields $received;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->received = $this->app->instance(ReceivedFields::class, new ReceivedFields);
        $this->useEngine(new ForeignEngine);
        config(['rest.extensions' => [ForeignEngineProfiles::class, RedEverywhereExtension::class]]);
    }

    #[Test]
    public function it_shows_a_row_that_the_restriction_excludes_since_indexing_until_the_index_syncs_it(): void
    {
        Voucher::query()->whereKey(3)->update(['team' => 'blue']);
        $this->captureQueries();

        $response = $this->search('vouchers', ['text' => ['value' => 'report']]);

        $response->assertOk()->assertExactJson(array_replace_recursive($this->page([3], 1), ['data' => [0 => ['team' => 'blue']]]));
        $this->assertSame([['select vouchers.id, vouchers.code, vouchers.number, vouchers.team, vouchers.title, vouchers.price, vouchers.active from vouchers where vouchers.code in (?) and vouchers.deleted_at is null', ['V-a']]], $this->readsOf('vouchers'));
    }

    #[Test]
    public function it_renders_a_row_that_the_restriction_excludes_since_indexing_outside_a_request(): void
    {
        Voucher::query()->whereKey(3)->update(['team' => 'blue']);

        $body = Rest::search(VoucherResource::class)->as(null)->payload(['text' => ['value' => 'report']])->render();

        $this->assertSame(Arr::except(array_replace_recursive($this->page([3], 1), ['data' => [0 => ['team' => 'blue']]]), ['meta']), Arr::except($body, ['meta']));
    }

    #[Test]
    public function it_counts_a_row_deleted_since_indexing_in_dropped(): void
    {
        Voucher::query()
            ->findOrFail(3)
            ->forceDelete();

        $response = $this->search('vouchers', ['text' => ['value' => 'report']]);

        $response->assertOk()->assertExactJson($this->page([], 1, dropped: 1));
    }

    #[Test]
    public function it_restricts_a_sync_action_on_rows_changed_since_indexing(): void
    {
        Voucher::query()->whereKey(3)->update(['team' => 'blue']);

        $response = $this->postJson('/api/vouchers/actions/retitle-vouchers', ['search' => ['text' => ['value' => 'report']]]);

        $response->assertOk()->assertExactJson(['data' => ['impacted' => 0, 'result' => null]]);
        $this->assertSame('Report beta', Voucher::query()->whereKey(3)->value('title'));
    }

    #[Test]
    public function it_does_not_queue_a_row_that_the_restriction_excludes_since_indexing(): void
    {
        Route::prefix('api')->group(fn () => Rest::resource('queued-vouchers', QueuedVoucherController::class)->register());
        Queue::fake();
        Voucher::query()->whereKey(3)->update(['team' => 'blue']);

        $response = $this->postJson('/api/queued-vouchers/actions/queued-retitle-vouchers', ['search' => ['text' => ['value' => 'report']]]);

        $response->assertOk()->assertExactJson(['data' => ['impacted' => 0, 'result' => null]]);
        Queue::assertNothingPushed();
    }

    #[Test]
    public function it_restricts_the_rows_of_a_queued_action_on_search_results_again(): void
    {
        Route::prefix('api')->group(fn () => Rest::resource('queued-vouchers', QueuedVoucherController::class)->register());
        Queue::fake();
        $this->postJson('/api/queued-vouchers/actions/queued-retitle-vouchers', ['search' => ['text' => ['value' => 'report']]])->assertOk();
        $job = Queue::pushed(QueuedAction::class)->sole();
        Voucher::query()->whereKey(3)->update(['team' => 'blue']);
        $this->captureQueries();

        $worker = unserialize(serialize($job));
        $worker->setJob(new SyncJob($this->app, '{}', 'sync', 'default'));
        $this->app?->call([$worker, 'handle']);

        $this->assertSame([3], $job->rows->keys);
        $this->assertSame([], $this->received->calls());
        $this->assertSame([['select * from vouchers where ((vouchers.team = ?)) and vouchers.id in (3) and vouchers.deleted_at is null', ['red']]], $this->readsOf('vouchers'));
        $this->assertSame('Report beta', Voucher::query()->whereKey(3)->value('title'));
    }
}
