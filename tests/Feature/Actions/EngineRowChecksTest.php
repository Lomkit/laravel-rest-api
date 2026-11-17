<?php

namespace Lomkit\Rest\Tests\Feature\Actions;

use Illuminate\Auth\GenericUser;
use Illuminate\Bus\PendingBatch;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Lomkit\Rest\Actions\QueuedAction;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Actions\Stubs\CheckedKeyedVoucherController;
use Lomkit\Rest\Tests\Feature\Actions\Stubs\CheckedNumberedVoucherController;
use Lomkit\Rest\Tests\Feature\Actions\Stubs\CheckedVoucherController;
use Lomkit\Rest\Tests\Feature\Actions\Stubs\LockedVoucherPolicy;
use Lomkit\Rest\Tests\Feature\Text\Engine\EngineSearchTestCase;
use Lomkit\Rest\Tests\Fixtures\Ledger\KeyedVoucher;
use Lomkit\Rest\Tests\Fixtures\Ledger\NumberedVoucher;
use Lomkit\Rest\Tests\Fixtures\Ledger\Voucher;
use Lomkit\Rest\Tests\Fixtures\ReceivedFields;
use Orchestra\Testbench\Attributes\WithConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;

#[Group('scout')]
#[WithConfig('app.debug', false)]
class EngineRowChecksTest extends EngineSearchTestCase
{
    private const array REPORT = ['search' => ['text' => ['value' => 'report']]];

    private const array ACTIVE = ['search' => ['text' => ['value' => 'report'], 'filters' => [['field' => 'active', 'value' => true]]]];

    private const array NUMBERED = ['search' => ['text' => ['value' => 'report'], 'filters' => [['field' => 'number', 'operator' => 'in', 'value' => [3, 4]]]]];

    private const string BATCH = '3c5e7a90-1b2d-4f6a-8c0e-2d4f6a8c0e1b';

    private ReceivedFields $received;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->received = $this->app->instance(ReceivedFields::class, new ReceivedFields);
        $this->actingAs(new GenericUser(['id' => 1, 'teams' => ['red', 'blue']]));

        foreach ([Voucher::class, NumberedVoucher::class, KeyedVoucher::class] as $model) {
            Gate::policy($model, LockedVoucherPolicy::class);
        }

        Route::prefix('api')->group(function (): void {
            Rest::resource('checked-vouchers', CheckedVoucherController::class)->register();
            Rest::resource('checked-numbered-vouchers', CheckedNumberedVoucherController::class)->register();
            Rest::resource('checked-keyed-vouchers', CheckedKeyedVoucherController::class)->register();
        });
    }

    #[Test]
    #[DataProvider('refusedRows')]
    public function it_checks_the_rows_that_the_engine_matches_before_the_action_runs(string $driver, string $resource): void
    {
        config(['scout.driver' => $driver]);

        $response = $this->postJson("/api/{$resource}/actions/lock-vouchers", self::REPORT);

        $response->assertForbidden()->assertExactJson(['message' => 'This action is unauthorized.']);
        $this->assertSame([], $this->received->calls());
    }

    /** @return iterable<string, array{string, string}> */
    public static function refusedRows(): iterable
    {
        foreach (self::drivers() as $engine => [$driver]) {
            yield "a string scout key on {$engine}" => [$driver, 'checked-vouchers'];
            yield "an integer scout key on {$engine}" => [$driver, 'checked-numbered-vouchers'];
            yield "a scout key that is the primary key on {$engine}" => [$driver, 'checked-keyed-vouchers'];
        }
    }

    /** @param list<list<int>> $chunks */
    #[Test]
    #[DataProvider('allowedRows')]
    public function it_runs_on_the_allowed_rows_that_the_engine_matches(string $driver, string $resource, array $chunks): void
    {
        config(['scout.driver' => $driver]);

        $response = $this->postJson("/api/{$resource}/actions/lock-allowed-vouchers", self::REPORT);

        $response->assertOk()->assertExactJson(['data' => ['impacted' => 3, 'result' => null]]);
        $this->assertSame(self::handled($chunks), $this->received->calls());
    }

    /** @return iterable<string, array{string, string, list<list<int>>}> */
    public static function allowedRows(): iterable
    {
        foreach (self::drivers() as $engine => [$driver]) {
            yield "a string scout key on {$engine}" => [$driver, 'checked-vouchers', [[4], [1, 3]]];
            yield "an integer scout key on {$engine}" => [$driver, 'checked-numbered-vouchers', [[4], [1, 3]]];
            yield "a scout key that is the primary key on {$engine}" => [$driver, 'checked-keyed-vouchers', [[4], [3, 1]]];
        }
    }

    /**
     * @param  array<string, mixed>  $body
     * @param  list<list<int>>  $chunks
     */
    #[Test]
    #[DataProvider('checkedRows')]
    public function it_runs_on_every_checked_row_that_the_engine_matches(string $driver, string $resource, array $body, int $impacted, array $chunks): void
    {
        config(['scout.driver' => $driver]);

        $response = $this->postJson("/api/{$resource}/actions/lock-vouchers", $body);

        $response->assertOk()->assertExactJson(['data' => ['impacted' => $impacted, 'result' => null]]);
        $this->assertSame(self::handled($chunks), $this->received->calls());
    }

    /** @return iterable<string, array{string, string, array<string, mixed>, int, list<list<int>>}> */
    public static function checkedRows(): iterable
    {
        foreach (self::drivers() as $engine => [$driver]) {
            yield "a string scout key on {$engine}" => [$driver, 'checked-vouchers', self::ACTIVE, 3, [[4, 1], [3]]];
            yield "an integer scout key on {$engine}" => [$driver, 'checked-numbered-vouchers', self::NUMBERED, 2, [[4, 1]]];
            yield "a scout key that is the primary key on {$engine}" => [$driver, 'checked-keyed-vouchers', self::ACTIVE, 3, [[4, 3], [1]]];
        }
    }

    #[Test]
    #[DataProvider('drivers')]
    public function it_queues_every_checked_row_that_the_engine_matches(string $driver): void
    {
        config(['scout.driver' => $driver]);
        $this->app['auth']->forgetGuards();
        Queue::fake();

        $response = $this->postJson('/api/checked-vouchers/actions/queued-lock-vouchers', self::ACTIVE);

        $response->assertOk()->assertExactJson(['data' => ['impacted' => 3, 'result' => null]]);
        $jobs = Queue::pushed(QueuedAction::class)->all();
        $this->assertSame([[4, 1], [3]], array_map(fn (QueuedAction $job): array => $job->rows->keys, $jobs));
        $this->assertSame([], $this->received->calls());

        array_map($this->work(...), $jobs);

        $this->assertSame(self::handled([[4, 1], [3]]), $this->received->calls());
    }

    #[Test]
    #[DataProvider('drivers')]
    public function it_batches_every_checked_row_that_the_engine_matches(string $driver): void
    {
        config(['scout.driver' => $driver]);
        $this->app['auth']->forgetGuards();
        Bus::fake();
        Str::createUuidsUsingSequence([Uuid::fromString(self::BATCH)]);

        $response = $this->postJson('/api/checked-vouchers/actions/batched-lock-vouchers', self::ACTIVE);

        $response->assertOk()->assertExactJson(['data' => ['impacted' => 3, 'batch' => self::BATCH, 'result' => null]]);
        $jobs = $this->batchedJobs();
        $this->assertSame([[4, 1], [3]], array_map(fn (QueuedAction $job): array => $job->rows->keys, $jobs));
        $this->assertSame([['withBatch', []]], $this->received->calls());

        array_map($this->work(...), $jobs);

        $this->assertSame([['withBatch', []], ...self::handled([[4, 1], [3]])], $this->received->calls());
    }

    #[Test]
    #[DataProvider('drivers')]
    public function it_runs_on_the_checked_rows_only_when_the_scout_keys_change_after_the_checks(string $driver): void
    {
        config(['scout.driver' => $driver]);
        $this->swapCodesWhileChecking();

        $response = $this->postJson('/api/checked-vouchers/actions/lock-vouchers', self::ACTIVE);

        $response->assertOk()->assertExactJson(['data' => ['impacted' => 2, 'result' => null]]);
        $this->assertSame(self::handled([[4], [3]]), $this->received->calls());
    }

    #[Test]
    #[DataProvider('drivers')]
    public function it_queues_the_checked_rows_only_when_the_scout_keys_change_after_the_checks(string $driver): void
    {
        config(['scout.driver' => $driver]);
        $this->app['auth']->forgetGuards();
        $this->swapCodesWhileChecking();
        Queue::fake();

        $response = $this->postJson('/api/checked-vouchers/actions/queued-lock-vouchers', self::ACTIVE);

        $response->assertOk()->assertExactJson(['data' => ['impacted' => 2, 'result' => null]]);
        $this->assertSame([[4], [3]], Queue::pushed(QueuedAction::class)->map(fn (QueuedAction $job): array => $job->rows->keys)->all());
    }

    #[Test]
    #[DataProvider('drivers')]
    public function it_batches_the_checked_rows_only_when_the_scout_keys_change_after_the_checks(string $driver): void
    {
        config(['scout.driver' => $driver]);
        $this->app['auth']->forgetGuards();
        $this->swapCodesWhileChecking();
        Bus::fake();
        Str::createUuidsUsingSequence([Uuid::fromString(self::BATCH)]);

        $response = $this->postJson('/api/checked-vouchers/actions/batched-lock-vouchers', self::ACTIVE);

        $response->assertOk()->assertExactJson(['data' => ['impacted' => 2, 'batch' => self::BATCH, 'result' => null]]);
        $this->assertSame([[4], [3]], array_map(fn (QueuedAction $job): array => $job->rows->keys, $this->batchedJobs()));
    }

    /**
     * @param  list<list<int>>  $chunks
     * @return list<array{string, array{ids: list<int>}}>
     */
    private static function handled(array $chunks): array
    {
        return array_map(fn (array $ids): array => ['handle', ['ids' => $ids]], $chunks);
    }

    private function swapCodesWhileChecking(): void
    {
        $this->app->instance(LockedVoucherPolicy::class, new class extends LockedVoucherPolicy
        {
            #[\Override]
            public function update(?Authenticatable $user, Model $model): bool
            {
                if ($model->getKey() === 1 && Voucher::query()->whereKey(5)->value('code') === 'V-e') {
                    Voucher::query()->whereKey(1)->update(['code' => 'V-z']);
                    Voucher::query()->whereKey(5)->update(['code' => 'V-c']);
                }

                return parent::update($user, $model);
            }
        });
    }

    /** @return list<QueuedAction> */
    private function batchedJobs(): array
    {
        return array_values(Bus::batched(fn (PendingBatch $batch): bool => $batch->name === 'batched-lock-vouchers')->sole()->jobs->all());
    }

    private function work(QueuedAction $job): void
    {
        $this->app->call([unserialize(serialize($job)), 'handle']);
    }
}
