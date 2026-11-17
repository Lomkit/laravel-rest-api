<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Restrictions;

use Illuminate\Bus\PendingBatch;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Queue\Jobs\SyncJob;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Lomkit\Rest\Actions\QueuedAction;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Concerns\CapturesQueries;
use Lomkit\Rest\Tests\Concerns\ResetsExtensionStubs;
use Lomkit\Rest\Tests\Feature\Events\Stubs\EventLog;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\ForeignEngineProfiles;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\RedEverywhereExtension;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\StaleRowsHooks;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\StaleVoucherController;
use Lomkit\Rest\Tests\Feature\Text\Engine\EngineSearchTestCase;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\ForeignEngine;
use Lomkit\Rest\Tests\Fixtures\Ledger\Voucher;
use Lomkit\Rest\Tests\Fixtures\Ledger\VoucherResource;
use Lomkit\Rest\Tests\Fixtures\ReceivedFields;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
class StaleEngineRowsTest extends EngineSearchTestCase
{
    use CapturesQueries;
    use ResetsExtensionStubs;

    private const array REPORT = ['text' => ['value' => 'report']];

    private ReceivedFields $received;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->received = $this->app->instance(ReceivedFields::class, new ReceivedFields);
        $this->useEngine(new ForeignEngine);
        config(['rest.extensions' => [ForeignEngineProfiles::class, RedEverywhereExtension::class]]);
        Route::prefix('api')->group(fn () => Rest::resource('stale-vouchers', StaleVoucherController::class)->register());
    }

    #[Test]
    public function it_shows_a_row_that_the_restriction_excludes_since_indexing_until_the_index_syncs_it(): void
    {
        Voucher::query()->whereKey(3)->update(['team' => 'blue']);
        $this->captureQueries();

        $response = $this->search('vouchers', self::REPORT);

        $response->assertOk()->assertExactJson(array_replace_recursive($this->page([3], 1), ['data' => [0 => ['team' => 'blue']]]));
        $this->assertSame([['select vouchers.id, vouchers.code, vouchers.number, vouchers.team, vouchers.title, vouchers.price, vouchers.active from vouchers where vouchers.code in (?) and vouchers.deleted_at is null', ['V-a']]], $this->readsOf('vouchers'));
    }

    #[Test]
    public function it_renders_a_row_that_the_restriction_excludes_since_indexing_outside_a_request(): void
    {
        Voucher::query()->whereKey(3)->update(['team' => 'blue']);

        $body = Rest::search(VoucherResource::class)->as(null)->payload(self::REPORT)->render();

        $this->assertSame(Arr::except(array_replace_recursive($this->page([3], 1), ['data' => [0 => ['team' => 'blue']]]), ['meta']), Arr::except($body, ['meta']));
    }

    #[Test]
    public function it_answers_the_policy_to_view_and_false_to_the_write_gates_of_a_row_that_the_restriction_excludes_since_indexing(): void
    {
        Voucher::query()->whereKey(3)->update(['team' => 'blue']);
        $this->captureQueries();

        $response = $this->search('stale-vouchers', [...self::REPORT, 'gates' => ['view', 'update', 'delete']]);

        $response->assertOk()->assertJsonPath('data.*.id', [3])->assertJsonPath('data.0.team', 'blue');
        $this->assertSame([
            'authorized_to_view' => ['allowed' => true, 'message' => null],
            'authorized_to_update' => ['allowed' => false, 'message' => null],
            'authorized_to_delete' => ['allowed' => false, 'message' => null],
        ], $response->json('data.0.gates'));
        $this->assertSame([
            ['select vouchers.id from vouchers where ((team = ? or team = ?) and (vouchers.team = ?)) and vouchers.id in (3) and vouchers.deleted_at is null', ['red', 'blue', 'red']],
        ], $this->keyQueries('vouchers'));
    }

    #[Test]
    public function it_hands_a_row_that_the_restriction_excludes_since_indexing_to_the_search_hooks(): void
    {
        config(['rest.extensions' => [ForeignEngineProfiles::class, RedEverywhereExtension::class, StaleRowsHooks::class]]);
        Voucher::query()->whereKey(3)->update(['team' => 'blue']);

        $response = $this->search('stale-vouchers', self::REPORT);

        $response->assertOk()->assertJsonPath('data.*.id', [3])->assertJsonPath('meta', ['rows' => ['rows' => '3 blue']]);
        $this->assertSame(['searched 3 blue', 'meta 3 blue', 'read 3 blue'], EventLog::$lines);
    }

    #[Test]
    public function it_counts_a_row_deleted_since_indexing_in_dropped(): void
    {
        Voucher::query()
            ->findOrFail(3)
            ->forceDelete();

        $response = $this->search('vouchers', self::REPORT);

        $response->assertOk()->assertExactJson($this->page([], 1, dropped: 1));
    }

    #[Test]
    public function it_restricts_a_sync_action_on_rows_changed_since_indexing(): void
    {
        Voucher::query()->whereKey(3)->update(['team' => 'blue']);

        $response = $this->postJson('/api/vouchers/actions/retitle-vouchers', ['search' => self::REPORT]);

        $response->assertOk()->assertExactJson(['data' => ['impacted' => 0, 'result' => null]]);
        $this->assertSame('Report beta', Voucher::query()->whereKey(3)->value('title'));
    }

    #[Test]
    public function it_does_not_queue_a_row_that_the_restriction_excludes_since_indexing(): void
    {
        Queue::fake();
        Voucher::query()->whereKey(3)->update(['team' => 'blue']);

        $response = $this->postJson('/api/stale-vouchers/actions/queued-retitle-vouchers', ['search' => self::REPORT]);

        $response->assertOk()->assertExactJson(['data' => ['impacted' => 0, 'result' => null]]);
        Queue::assertNothingPushed();
    }

    /** @param list<list<int>> $jobs */
    #[Test]
    #[DataProvider('teams')]
    public function it_hands_the_batch_only_the_rows_that_the_restriction_keeps(string $team, int $impacted, array $jobs): void
    {
        Bus::fake();
        Voucher::query()->whereKey(3)->update(['team' => $team]);

        $response = $this->postJson('/api/stale-vouchers/actions/batched-retitle-vouchers', ['search' => self::REPORT]);

        $response->assertOk()->assertJsonPath('data.impacted', $impacted);
        Bus::assertBatchCount(1);
        Bus::assertBatched(fn (PendingBatch $batch): bool => $batch->jobs->map(fn (QueuedAction $job): array => $job->rows->keys)->all() === $jobs);
        $this->assertSame([], $this->received->calls());
    }

    /** @param list<array{string, int}> $checks */
    #[Test]
    #[DataProvider('checkedTeams')]
    public function it_checks_and_handles_only_the_rows_that_the_restriction_keeps(string $action, string $team, int $impacted, array $checks): void
    {
        $checked = [];
        Gate::after(function (?Authenticatable $user, string $ability, mixed $result, array $arguments) use (&$checked): void {
            if (($arguments[0] ?? null) instanceof Voucher) {
                $checked[] = [$ability, $arguments[0]->getKey()];
            }
        });
        Voucher::query()->whereKey(3)->update(['team' => $team]);

        $response = $this->postJson("/api/stale-vouchers/actions/{$action}", ['search' => self::REPORT]);

        $response->assertOk()->assertExactJson(['data' => ['impacted' => $impacted, 'result' => null]]);
        $this->assertSame($checks, $checked);
        $this->assertSame($impacted === 0 ? [] : [['handle', ['ids' => [3]]]], $this->received->calls());
    }

    /** @return array<string, array{string, int, list<list<int>>}> */
    public static function teams(): array
    {
        return [
            'a row the restriction still keeps' => ['red', 1, [[3]]],
            'a row the restriction excludes since indexing' => ['blue', 0, []],
        ];
    }

    /** @return array<string, array{string, string, int, list<array{string, int}>}> */
    public static function checkedTeams(): array
    {
        return [
            'a skipping action on a row the restriction still keeps' => ['lock-allowed-vouchers', 'red', 1, [['update', 3]]],
            'a skipping action on a row the restriction excludes since indexing' => ['lock-allowed-vouchers', 'blue', 0, []],
            'a refusing action on a row the restriction excludes since indexing' => ['lock-vouchers', 'blue', 0, []],
        ];
    }

    #[Test]
    public function it_restricts_the_rows_of_a_queued_action_on_search_results_again(): void
    {
        Queue::fake();
        $this->postJson('/api/stale-vouchers/actions/queued-retitle-vouchers', ['search' => self::REPORT])->assertOk();
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
