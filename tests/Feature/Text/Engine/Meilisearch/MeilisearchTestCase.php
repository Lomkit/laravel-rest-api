<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine\Meilisearch;

use Illuminate\Http\Client\Factory;
use Laravel\Scout\Engines\MeilisearchEngine;
use Lomkit\Rest\Tests\Feature\Text\Engine\EngineSearchTestCase;
use Lomkit\Rest\Tests\Fixtures\Ledger\Voucher;
use Orchestra\Testbench\Attributes\RequiresEnv;
use Orchestra\Testbench\Attributes\WithConfig;

#[RequiresEnv('MEILISEARCH_HOST')]
#[WithConfig('scout.driver', 'meilisearch')]
abstract class MeilisearchTestCase extends EngineSearchTestCase
{
    private static int $tests = 0;

    private static ?string $run = null;

    protected MeilisearchIndexes $indexes;

    protected string $index;

    #[\Override]
    protected function defineEnvironment($app): void
    {
        $app['config']->set([
            'scout.meilisearch.host' => self::host(),
            'scout.meilisearch.key' => self::key(),
            'scout.queue' => false,
            'scout.after_commit' => false,
            'scout.soft_delete' => true,
            'scout.prefix' => self::prefix(),
        ]);
    }

    #[\Override]
    protected function seedLedger(): void
    {
        $this->assertSame(MeilisearchEngine::class, (new Voucher)->searchableUsing()::class);

        $this->indexes = new MeilisearchIndexes(new Factory, self::host(), self::key());
        $this->index = (new Voucher)->searchableAs();
        $this->beforeApplicationDestroyed(fn () => $this->indexes->drop($this->index));
        $this->indexes->reset($this->index, (new Voucher)->getScoutKeyName(), [
            'filterableAttributes' => ['number', 'price', 'active', 'code', 'team', 'title', '__soft_deleted'],
            'sortableAttributes' => ['price', 'number', 'code'],
            'typoTolerance' => ['enabled' => false],
        ]);

        parent::seedLedger();

        $this->settle();
    }

    protected function settle(): void
    {
        $this->indexes->settle($this->index);
    }

    private static function prefix(): string
    {
        self::$run ??= bin2hex(random_bytes(4));

        return sprintf('lrapi_%d_%s_%d_', getmypid(), self::$run, ++self::$tests);
    }

    private static function host(): string
    {
        $host = env('MEILISEARCH_HOST');

        return is_string($host) ? $host : '';
    }

    private static function key(): ?string
    {
        $key = env('MEILISEARCH_KEY');

        return is_string($key) ? $key : null;
    }
}
