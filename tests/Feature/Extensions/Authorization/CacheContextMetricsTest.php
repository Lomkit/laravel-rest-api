<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Authorization;

use Illuminate\Auth\GenericUser;
use Illuminate\Cache\Events\KeyWritten;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Lomkit\Rest\Extensions\Contexts\CacheContributionContext;
use Lomkit\Rest\Metrics\CacheScope;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\FirstCacheContext;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\TenantCacheContext;
use Lomkit\Rest\Tests\Feature\Metrics\SalesTestCase;
use Lomkit\Rest\Tests\Feature\Metrics\Stubs\MetricOrderResource;
use Lomkit\Rest\Tests\Feature\Metrics\Stubs\TotalRevenue;
use Orchestra\Testbench\Attributes\WithConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

#[WithConfig('cache.serializable_classes', false)]
class CacheContextMetricsTest extends SalesTestCase
{
    /** @var array<string, int> */
    private array $written = [];

    private int $statements = 0;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        FirstCacheContext::reset();
        FirstCacheContext::$resources = [MetricOrderResource::class];
        $this->actingAs(new GenericUser(['id' => 1, 'team_id' => 1]));
        Event::listen(KeyWritten::class, function (KeyWritten $event): void {
            if (str_starts_with($event->key, 'rest.metrics.') && $event->seconds !== null) {
                $this->written[$event->key] = $event->seconds;
            }
        });
        DB::listen(function (QueryExecuted $query): void {
            $this->statements += preg_match('/from\s+orders\b/i', str_replace(['"', '`'], '', $query->sql));
        });
    }

    #[\Override]
    protected function tearDown(): void
    {
        FirstCacheContext::reset();

        parent::tearDown();
    }

    #[Test]
    #[DataProvider('scopesAndStores')]
    public function it_never_serves_the_result_of_another_tenant(CacheScope $scope, string $store): void
    {
        $this->useStore($store);
        $this->serve(TotalRevenue::make()->cacheFor(600)->cacheScope($scope));
        config(['rest.extensions' => [TenantCacheContext::class]]);
        $tenant = 0;
        FirstCacheContext::$contribute = static function (CacheContributionContext $context) use (&$tenant): void {
            $context->put('id', $tenant);
        };

        $statements = [];

        foreach ([1, 2, 1, 2] as $tenant) {
            $statements[] = $this->statementsOf('total-revenue');
        }

        $this->assertGreaterThan(0, $statements[0]);
        $this->assertSame([$statements[0], $statements[0], 0, 0], $statements);
        $this->assertCount(2, $this->written);
    }

    #[Test]
    #[DataProvider('scopesAndStores')]
    public function it_never_serves_a_result_after_the_rules_fingerprint_changes(CacheScope $scope, string $store): void
    {
        $this->useStore($store);
        $this->serve(TotalRevenue::make()->cacheFor(600)->cacheScope($scope));
        config(['rest.extensions' => [FirstCacheContext::class]]);
        $rules = '';
        FirstCacheContext::$contribute = static function (CacheContributionContext $context) use (&$rules): void {
            $context->put('rules', $rules);
        };

        $statements = [];

        foreach (['v1', 'v2', 'v2', 'v1'] as $rules) {
            $statements[] = $this->statementsOf('total-revenue');
        }

        $this->assertGreaterThan(0, $statements[0]);
        $this->assertSame([$statements[0], $statements[0], 0, 0], $statements);
    }

    /** @return iterable<string, array{CacheScope, string}> */
    public static function scopesAndStores(): iterable
    {
        foreach ([CacheScope::User, CacheScope::Global] as $scope) {
            foreach (['array', 'database'] as $store) {
                yield "{$scope->value} scope, {$store} store" => [$scope, $store];
            }
        }
    }

    #[Test]
    public function it_keeps_the_metric_key_of_a_resource_without_an_item(): void
    {
        $this->serve(TotalRevenue::make()->cacheFor(600));

        $plain = $this->statementsOf('total-revenue');
        config(['rest.extensions' => [FirstCacheContext::class]]);
        $itemless = $this->statementsOf('total-revenue');
        $written = array_keys($this->written);
        FirstCacheContext::$contribute = static fn (CacheContributionContext $context) => $context->put('tenant', 1);
        $keyed = $this->statementsOf('total-revenue');

        $this->assertGreaterThan(0, $plain);
        $this->assertSame([$plain, 0, $plain], [$plain, $itemless, $keyed]);
        $this->assertCount(1, $written);
        $this->assertCount(2, $this->written);
        $this->assertCount(2, FirstCacheContext::$contexts);
    }

    #[Test]
    public function it_keeps_the_duration_of_the_metric(): void
    {
        $this->serve(TotalRevenue::make()->cacheFor(600));
        config(['rest.extensions' => [FirstCacheContext::class]]);
        FirstCacheContext::$contribute = static function (CacheContributionContext $context): void {
            $context->shorten('viewAny', 1);
            $context->forget('view');
        };

        $this->metric('total-revenue')->assertOk();

        $this->assertSame([600], array_values($this->written));
    }

    #[Test]
    public function it_calls_the_extension_once_per_request(): void
    {
        $this->serve(TotalRevenue::make()->cacheFor(600)->cacheScope(CacheScope::Global));
        config(['rest.extensions' => [FirstCacheContext::class]]);

        $this->metric('total-revenue')->assertOk();

        $this->assertCount(1, FirstCacheContext::$contexts);
    }

    private function statementsOf(string $metric): int
    {
        $this->statements = 0;
        $this->metric($metric)->assertOk();

        return $this->statements;
    }

    private function useStore(string $store): void
    {
        config(['rest.authorizations.cache.store' => $store, 'cache.stores.database.connection' => null]);
    }
}
