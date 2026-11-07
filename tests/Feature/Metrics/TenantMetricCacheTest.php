<?php

namespace Lomkit\Rest\Tests\Feature\Metrics;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Metrics\Stubs\MetricOrderController;
use Lomkit\Rest\Tests\Feature\Metrics\Stubs\MetricOrderResource;
use Lomkit\Rest\Tests\Feature\Metrics\Stubs\SharedOrderCount;
use Lomkit\Rest\Tests\Feature\Metrics\Stubs\TenantOrderController;
use Lomkit\Rest\Tests\Fixtures\Sales\Order;
use Lomkit\Rest\Tests\Fixtures\Sales\Sales;
use Lomkit\Rest\Tests\Fixtures\Tenancy\Tenancy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class TenantMetricCacheTest extends SalesTestCase
{
    private const string TENANT_A = '{"data":{"type":"value","value":2,"previous":null}}';

    private const string TENANT_B = '{"data":{"type":"value","value":1,"previous":null}}';

    #[\Override]
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        foreach (['tenant_a', 'tenant_b'] as $connection) {
            $app['config']->set("database.connections.{$connection}", ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        }
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $orders = [
            'tenant_a' => [[1, 'A-1', '2026-11-01 09:00:00'], [2, 'A-2', '2026-11-02 09:00:00']],
            'tenant_b' => [[1, 'B-1', '2026-11-03 09:00:00']],
        ];

        foreach ($orders as $connection => $rows) {
            Artisan::call('migrate', ['--database' => $connection, '--path' => (new Sales)->migrations(), '--realpath' => true]);

            foreach ($rows as [$id, $reference, $placedAt]) {
                Order::on($connection)->create(['id' => $id, 'team_id' => 1, 'reference' => $reference, 'status' => 'paid', 'price' => '10.00', 'quantity' => 1, 'gift' => false, 'placed_at' => $placedAt]);
            }
        }

        Tenancy::$connections = ['a' => 'tenant_a', 'b' => 'tenant_b'];
    }

    #[\Override]
    protected function tearDown(): void
    {
        Tenancy::forget();

        parent::tearDown();
    }

    /** @param list<string> $contents */
    #[Test]
    #[DataProvider('contexts')]
    public function it_keeps_a_global_result_per_tenant_when_the_context_holds_the_tenant(string $controller, array $contents, int $statements): void
    {
        MetricOrderResource::$declared = [SharedOrderCount::make()];
        Rest::resource('tenant-orders', $controller)->register();
        $count = 0;
        DB::listen(function (QueryExecuted $query) use (&$count): void {
            $count += str_contains($query->sql, 'orders') ? 1 : 0;
        });
        $answers = [];

        foreach (['a', 'b', 'a', 'b'] as $tenant) {
            Tenancy::initialize($tenant);
            $answers[] = $this->postJson('/tenant-orders/metrics/shared-order-count')->getContent();
        }

        $this->assertSame([$contents, $statements], [$answers, $count]);
    }

    /** @return iterable<string, array{string, list<string>, int}> */
    public static function contexts(): iterable
    {
        yield 'the tenant in the context' => [TenantOrderController::class, [self::TENANT_A, self::TENANT_B, self::TENANT_A, self::TENANT_B], 2];
        yield 'no tenant in the context' => [MetricOrderController::class, [self::TENANT_A, self::TENANT_A, self::TENANT_A, self::TENANT_A], 1];
    }
}
