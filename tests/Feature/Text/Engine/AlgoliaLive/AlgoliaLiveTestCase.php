<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine\AlgoliaLive;

use Algolia\AlgoliaSearch\Api\SearchClient;
use Illuminate\Support\Facades\Route;
use Laravel\Scout\Engines\Algolia4Engine;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Text\Engine\EngineSearchTestCase;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\UnsortedVoucherController;
use Lomkit\Rest\Tests\Fixtures\Ledger\ReaderVoucher;
use Lomkit\Rest\Tests\Fixtures\Ledger\Voucher;
use Orchestra\Testbench\Attributes\RequiresEnv;
use Orchestra\Testbench\Attributes\WithConfig;

#[RequiresEnv('ALGOLIA_APP_ID')]
#[RequiresEnv('ALGOLIA_SECRET')]
#[WithConfig('scout.driver', 'algolia')]
abstract class AlgoliaLiveTestCase extends EngineSearchTestCase
{
    private static ?string $run = null;

    private static int $tests = 0;

    protected SearchClient $client;

    protected string $index;

    #[\Override]
    protected function defineEnvironment($app): void
    {
        self::$run ??= bin2hex(random_bytes(4));

        $app['config']->set([
            'scout.algolia' => ['id' => self::env('ALGOLIA_APP_ID'), 'secret' => self::env('ALGOLIA_SECRET'), 'index-settings' => []],
            'scout.identify' => false,
            'scout.queue' => false,
            'scout.after_commit' => false,
            'scout.soft_delete' => true,
            'scout.prefix' => sprintf('lrapi_live_%s_%d_', self::$run, ++self::$tests),
        ]);
    }

    #[\Override]
    protected function defineRoutes($router): void
    {
        parent::defineRoutes($router);

        Route::prefix('api')->group(fn () => Rest::resource('unsorted-vouchers', UnsortedVoucherController::class)->withSoftDeletes());
    }

    #[\Override]
    protected function seedLedger(): void
    {
        $this->assertSame(Algolia4Engine::class, (new Voucher)->searchableUsing()::class);

        $this->client = SearchClient::create(self::env('ALGOLIA_APP_ID'), self::env('ALGOLIA_SECRET'));
        $this->index = (new Voucher)->searchableAs();
        $this->beforeApplicationDestroyed(fn () => $this->client->deleteIndex($this->index));

        Voucher::withoutSyncingToSearch(fn () => parent::seedLedger());

        $this->configure(['searchableAttributes' => ['title'], 'attributesForFaceting' => ['team', 'active', 'number', 'price', 'code', 'filterOnly(readers)', 'filterOnly(__soft_deleted)']]);
        $this->index(ReaderVoucher::query()->withTrashed()->orderBy('id')->get()->all());
    }

    /** @param array<string, mixed> $settings */
    protected function configure(array $settings): void
    {
        $this->wait($this->client->setSettings($this->index, $settings));
    }

    protected function wait(mixed $task): void
    {
        $id = is_array($task) ? $task['taskID'] ?? null : (is_object($task) && method_exists($task, 'getTaskID') ? $task->getTaskID() : null);

        if (is_int($id)) {
            $this->client->waitForTask($this->index, $id);
        }
    }

    /** @param list<ReaderVoucher> $vouchers */
    protected function index(array $vouchers): void
    {
        $objects = array_map(static fn (ReaderVoucher $voucher): array => [...$voucher->toSearchableArray(), 'objectID' => $voucher->getScoutKey(), '__soft_deleted' => $voucher->trashed() ? 1 : 0], $vouchers);

        $this->client->saveObjects($this->index, $objects, true);
    }

    private static function env(string $key): string
    {
        $value = env($key);

        return is_string($value) ? $value : '';
    }
}
