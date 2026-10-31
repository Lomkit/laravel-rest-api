<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine\Typesense;

use Illuminate\Http\Client\Factory;
use Laravel\Scout\Engines\TypesenseEngine;
use Lomkit\Rest\Tests\Feature\Text\Engine\EngineSearchTestCase;
use Lomkit\Rest\Tests\Fixtures\Ledger\NumberedVoucher;
use Lomkit\Rest\Tests\Fixtures\Ledger\ScopedVoucher;
use Lomkit\Rest\Tests\Fixtures\Ledger\Voucher;
use Orchestra\Testbench\Attributes\RequiresEnv;
use Orchestra\Testbench\Attributes\WithConfig;

#[RequiresEnv('TYPESENSE_HOST')]
#[WithConfig('scout.driver', 'typesense')]
abstract class TypesenseTestCase extends EngineSearchTestCase
{
    private const array SCHEMA = [
        'collection-schema' => ['fields' => [
            ['name' => 'code', 'type' => 'string', 'sort' => true],
            ['name' => 'number', 'type' => 'int32'],
            ['name' => 'team', 'type' => 'string'],
            ['name' => 'title', 'type' => 'string'],
            ['name' => 'price', 'type' => 'float'],
            ['name' => 'active', 'type' => 'bool'],
            ['name' => '__soft_deleted', 'type' => 'int32'],
        ]],
        'search-parameters' => ['query_by' => 'title'],
    ];

    private static int $tests = 0;

    protected TypesenseCollections $collections;

    protected string $collection;

    #[\Override]
    protected function defineEnvironment($app): void
    {
        $node = ['host' => self::env('TYPESENSE_HOST'), 'port' => self::env('TYPESENSE_PORT', '8108'), 'path' => '', 'protocol' => self::env('TYPESENSE_PROTOCOL', 'http')];

        $app['config']->set([
            'scout.typesense' => [
                'client-settings' => ['api_key' => self::env('TYPESENSE_API_KEY'), 'nodes' => [$node], 'connection_timeout_seconds' => 5, 'num_retries' => 0],
                'max_total_results' => 1000,
                'model-settings' => [Voucher::class => self::SCHEMA, NumberedVoucher::class => self::SCHEMA, ScopedVoucher::class => self::SCHEMA],
                'import_action' => 'upsert',
            ],
            'scout.queue' => false,
            'scout.after_commit' => false,
            'scout.soft_delete' => true,
            'scout.prefix' => sprintf('lrapi_%d_%d_', getmypid(), ++self::$tests),
        ]);
    }

    #[\Override]
    protected function seedLedger(): void
    {
        $this->assertSame(TypesenseEngine::class, (new Voucher)->searchableUsing()::class);

        $url = sprintf('%s://%s:%s/', self::env('TYPESENSE_PROTOCOL', 'http'), self::env('TYPESENSE_HOST'), self::env('TYPESENSE_PORT', '8108'));
        $this->collections = new TypesenseCollections(new Factory, $url, self::env('TYPESENSE_API_KEY'));
        $this->collection = (new Voucher)->searchableAs();
        $this->beforeApplicationDestroyed(fn () => $this->collections->drop($this->collection));
        $this->collections->drop($this->collection);

        parent::seedLedger();
    }

    private static function env(string $key, string $default = ''): string
    {
        $value = env($key);

        return is_string($value) && $value !== '' ? $value : $default;
    }
}
