<?php

namespace Lomkit\Rest\Tests\Feature\Metrics;

use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Metrics\Metric;
use Lomkit\Rest\Tests\Feature\Metrics\Stubs\MetricOrderController;
use Lomkit\Rest\Tests\Feature\Metrics\Stubs\MetricOrderResource;
use Lomkit\Rest\Tests\Fixtures\Sales\Order;
use Lomkit\Rest\Tests\Fixtures\Sales\Sales;
use Lomkit\Rest\Tests\PackageTestCase;
use Orchestra\Testbench\Attributes\WithConfig;

#[WithConfig('app.debug', false)]
abstract class SalesTestCase extends PackageTestCase
{
    protected const string NOW = '2026-11-07 12:00:00';

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(self::NOW);
        MetricOrderResource::$declared = [];
        $this->seedOrders();
    }

    #[\Override]
    protected function fixtures(): array
    {
        return [new Sales];
    }

    protected function serve(Metric ...$metrics): void
    {
        MetricOrderResource::$declared = array_values($metrics);
        Rest::resource('metric-orders', MetricOrderController::class)->register();
    }

    /** @param array<string, mixed> $body */
    protected function metric(string $key, array $body = []): TestResponse
    {
        return $this->postJson("/metric-orders/metrics/{$key}", $body);
    }

    protected function logQueries(): void
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
    }

    private function seedOrders(): void
    {
        $rows = [
            [1, 1, 'A-1001', 'paid', 'web', '20.00', 2, false, '2026-11-01 09:00:00'],
            [2, 1, 'A-1002', 'paid', 'shop', '12.50', 1, true, '2026-10-20 15:30:00'],
            [3, 1, 'A-1003', 'pending', 'web', '19.50', 3, false, '2026-10-08 12:00:00'],
            [4, 1, 'A-1004', 'refunded', null, '7.25', 1, false, '2026-10-08 11:59:59'],
            [5, 1, 'A-1005', null, 'web', '30.00', 4, true, '2026-09-15 10:00:00'],
            [6, 1, 'A-1006', 'paid', 'shop', '45.00', 2, false, '2026-09-08 11:59:59'],
            [7, 1, 'A-1007', 'paid', 'web', '99.00', 9, false, '2026-11-07 12:00:01'],
            [8, 2, 'B-2001', 'paid', 'web', '500.00', 50, true, '2026-11-02 08:00:00'],
        ];

        foreach ($rows as [$id, $team, $reference, $status, $channel, $price, $quantity, $gift, $placedAt]) {
            Order::create(['id' => $id, 'team_id' => $team, 'reference' => $reference, 'status' => $status, 'channel' => $channel, 'price' => $price, 'quantity' => $quantity, 'gift' => $gift, 'placed_at' => $placedAt]);
        }
    }
}
