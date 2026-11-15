<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Restrictions;

use Illuminate\Queue\Jobs\SyncJob;
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
    public function it_drops_a_row_that_the_restriction_excludes_since_indexing(): void
    {
        Voucher::query()->whereKey(3)->update(['team' => 'blue']);

        $response = $this->search('vouchers', ['text' => ['value' => 'report']]);

        $response->assertOk()->assertExactJson($this->page([], 1));
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
