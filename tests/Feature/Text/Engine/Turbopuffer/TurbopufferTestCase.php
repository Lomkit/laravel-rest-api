<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine\Turbopuffer;

use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Laravel\Scout\Engines\TurbopufferEngine;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Text\Engine\EngineSearchTestCase;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\UnsortedVoucherController;
use Lomkit\Rest\Tests\Fixtures\Ledger\Voucher;
use Orchestra\Testbench\Attributes\WithConfig;

#[WithConfig('scout.driver', 'turbopuffer')]
abstract class TurbopufferTestCase extends EngineSearchTestCase
{
    private const array USAGE = ['billing' => ['billable_logical_bytes_queried' => 0, 'billable_logical_bytes_returned' => 0], 'performance' => ['server_total_ms' => 1]];

    private static int $tests = 0;

    protected string $namespace;

    /** @var list<string> */
    private array $rows = [];

    private int $count = 0;

    #[\Override]
    protected function defineEnvironment($app): void
    {
        $app['config']->set([
            'scout.turbopuffer' => [
                'api_key' => 'test-key',
                'region' => 'gcp-us-central1',
                'timeout' => 5,
                'connect_timeout' => 5,
                'retries' => 0,
                'model-settings' => [Voucher::class => ['searchable-attributes' => ['title']]],
            ],
            'scout.queue' => false,
            'scout.after_commit' => false,
            'scout.soft_delete' => true,
            'scout.prefix' => sprintf('lrapi_%d_%d_', getmypid(), ++self::$tests),
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
        Http::preventStrayRequests();
        Http::fake(fn (Request $request): PromiseInterface => $this->respond($request));

        $this->assertSame(TurbopufferEngine::class, (new Voucher)->searchableUsing()::class);

        $this->namespace = (new Voucher)->searchableAs();

        Voucher::withoutSyncingToSearch(fn () => parent::seedLedger());
    }

    /** @param list<string> $rows */
    protected function rows(array $rows, int $count): void
    {
        $this->rows = $rows;
        $this->count = $count;
    }

    /** @return list<array<string, mixed>> */
    protected function queries(): array
    {
        $queries = [];

        foreach (Http::recorded() as [$request]) {
            if ($request->url() === "https://gcp-us-central1.turbopuffer.com/v2/namespaces/{$this->namespace}/query") {
                $queries[] = $request->data();
            }
        }

        return $queries;
    }

    private function respond(Request $request): PromiseInterface
    {
        if (! str_ends_with($request->url(), '/query')) {
            return Http::response(['status' => 'OK', 'rows_affected' => 1]);
        }

        if (isset($request->data()['aggregate_by'])) {
            return Http::response(['aggregations' => ['count' => $this->count], ...self::USAGE]);
        }

        $limit = $request->data()['limit'] ?? 10;
        $rows = array_slice($this->rows, 0, is_int($limit) ? $limit : 10);

        return Http::response(['rows' => array_map(fn (string $id): array => ['$dist' => 1.5, 'id' => $id], $rows), ...self::USAGE]);
    }
}
