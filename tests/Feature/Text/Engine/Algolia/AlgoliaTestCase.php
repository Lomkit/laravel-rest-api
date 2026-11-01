<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine\Algolia;

use Algolia\AlgoliaSearch\Algolia;
use Illuminate\Support\Facades\Route;
use Laravel\Scout\Engines\Algolia4Engine;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Text\Engine\EngineSearchTestCase;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\UnsortedVoucherController;
use Lomkit\Rest\Tests\Fixtures\Ledger\Voucher;
use Orchestra\Testbench\Attributes\WithConfig;

#[WithConfig('scout.driver', 'algolia')]
abstract class AlgoliaTestCase extends EngineSearchTestCase
{
    private static int $tests = 0;

    protected AlgoliaRequests $algolia;

    protected string $index;

    #[\Override]
    protected function defineEnvironment($app): void
    {
        $app['config']->set([
            'scout.algolia' => ['id' => 'LRAPITESTS', 'secret' => 'test-secret', 'index-settings' => []],
            'scout.identify' => false,
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
        $this->algolia = new AlgoliaRequests;
        Algolia::setHttpClient($this->algolia);
        $this->beforeApplicationDestroyed(fn () => Algolia::resetHttpClient());

        $this->assertSame(Algolia4Engine::class, (new Voucher)->searchableUsing()::class);

        $this->index = (new Voucher)->searchableAs();
        $this->algolia->respond('POST', "/1/indexes/{$this->index}/batch", 200, ['taskID' => 1, 'objectIDs' => []]);

        Voucher::withoutSyncingToSearch(fn () => parent::seedLedger());
    }

    /** @param list<string> $keys */
    protected function hits(array $keys, int $total, int $page = 0, int $perPage = 50): void
    {
        $this->algolia->respond('POST', "/1/indexes/{$this->index}/query", 200, [
            'hits' => array_map(fn (string $key): array => ['code' => $key, 'objectID' => $key, '_highlightResult' => []], $keys),
            'nbHits' => $total,
            'page' => $page,
            'nbPages' => (int) ceil($total / $perPage),
            'hitsPerPage' => $perPage,
            'exhaustiveNbHits' => true,
            'query' => 'report',
            'params' => '',
            'processingTimeMS' => 1,
        ]);
    }
}
