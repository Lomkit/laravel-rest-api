<?php

namespace Lomkit\Rest\Tests\Feature\Scaffolding;

use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Metrics\Metric;
use Lomkit\Rest\Tests\Feature\Scaffolding\Hosts\GeneratedMetricController;
use Lomkit\Rest\Tests\Feature\Scaffolding\Hosts\GeneratedMetricResource;
use Lomkit\Rest\Tests\Fixtures\Sales\Order;
use Lomkit\Rest\Tests\Fixtures\Sales\Sales;
use Lomkit\Rest\Tests\ScaffoldingTestCase;
use Orchestra\Testbench\Attributes\WithConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

#[WithConfig('app.debug', false)]
class GeneratedMetricRequestsTest extends ScaffoldingTestCase
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-11-07 12:00:00');
        $this->seedOrders();
    }

    #[\Override]
    protected function tearDown(): void
    {
        GeneratedMetricResource::$declared = [];

        parent::tearDown();
    }

    #[\Override]
    protected function fixtures(): array
    {
        return [new Sales];
    }

    /**
     * @param  array<string, string>  $options
     * @param  array<string, mixed>  $entry
     * @param  array<string, mixed>  $data
     */
    #[Test]
    #[DataProvider('metrics')]
    public function it_serves_a_generated_metric_from_its_own_route(string $name, array $options, array $entry, array $data): void
    {
        $this->artisan('rest:metric', ['name' => $name, ...$options, '--no-interaction' => true])->assertSuccessful();
        $class = "App\\Rest\\Metrics\\{$name}";
        $this->loadGeneratedClass($class);
        $this->assertTrue(is_subclass_of($class, Metric::class));

        GeneratedMetricResource::$declared = [$class];
        Rest::resource('generated-orders', GeneratedMetricController::class)->register();

        $this->getJson('/generated-orders?with=metrics.meta,metrics.authorized')
            ->assertOk()
            ->assertJsonPath('data.metrics', [$entry]);
        $this->postJson("/generated-orders/metrics/{$entry['uriKey']}")
            ->assertOk()
            ->assertExactJson(['data' => $data]);
    }

    /** @return array<string, array{string, array<string, string>, array<string, mixed>, array<string, mixed>}> */
    public static function metrics(): array
    {
        return [
            'value' => [
                'ServedOrderCount',
                ['--type' => 'value'],
                ['name' => 'Served Order Count', 'uriKey' => 'served-order-count', 'type' => 'value', 'ranges' => [], 'meta' => [], 'authorized' => true],
                ['type' => 'value', 'value' => 5, 'previous' => null],
            ],
            'trend' => [
                'ServedOrdersPerDay',
                ['--type' => 'trend'],
                ['name' => 'Served Orders Per Day', 'uriKey' => 'served-orders-per-day', 'type' => 'trend', 'ranges' => [['value' => 7, 'label' => '7 days'], ['value' => 30, 'label' => '30 days'], ['value' => 90, 'label' => '90 days']], 'meta' => [], 'authorized' => true],
                ['type' => 'trend', 'series' => [
                    ['label' => '2026-11-01', 'value' => 1],
                    ['label' => '2026-11-02', 'value' => 0],
                    ['label' => '2026-11-03', 'value' => 0],
                    ['label' => '2026-11-04', 'value' => 0],
                    ['label' => '2026-11-05', 'value' => 0],
                    ['label' => '2026-11-06', 'value' => 2],
                    ['label' => '2026-11-07', 'value' => 1],
                ]],
            ],
            'partition' => [
                'ServedOrdersByStatus',
                ['--type' => 'partition', '--group-by' => 'status'],
                ['name' => 'Served Orders By Status', 'uriKey' => 'served-orders-by-status', 'type' => 'partition', 'ranges' => [], 'meta' => [], 'authorized' => true],
                ['type' => 'partition', 'parts' => [
                    ['key' => 'paid', 'label' => 'Paid', 'value' => 2],
                    ['key' => 'pending', 'label' => 'Pending', 'value' => 2],
                    ['key' => null, 'label' => null, 'value' => 1],
                ], 'other' => null],
            ],
            'progress' => [
                'ServedOrderGoal',
                ['--type' => 'progress'],
                ['name' => 'Served Order Goal', 'uriKey' => 'served-order-goal', 'type' => 'progress', 'ranges' => [], 'meta' => [], 'authorized' => true],
                ['type' => 'progress', 'value' => 5, 'target' => 100],
            ],
            'table' => [
                'ServedTopChannels',
                ['--type' => 'table', '--group-by' => 'channel'],
                ['name' => 'Served Top Channels', 'uriKey' => 'served-top-channels', 'type' => 'table', 'ranges' => [], 'meta' => [], 'authorized' => true],
                ['type' => 'table', 'columns' => [
                    ['name' => 'count', 'label' => 'Count', 'aggregate' => 'count'],
                ], 'rows' => [
                    ['key' => 'web', 'label' => 'web', 'values' => ['count' => 3]],
                    ['key' => 'shop', 'label' => 'shop', 'values' => ['count' => 1]],
                    ['key' => null, 'label' => null, 'values' => ['count' => 1]],
                ]],
            ],
            'custom' => [
                'ServedOrderBacklog',
                ['--type' => 'custom'],
                ['name' => 'Served Order Backlog', 'uriKey' => 'served-order-backlog', 'type' => 'served-order-backlog', 'ranges' => [], 'meta' => [], 'authorized' => true],
                ['type' => 'served-order-backlog', 'value' => 5, 'previous' => null],
            ],
        ];
    }

    private function seedOrders(): void
    {
        $rows = [
            [1, 1, 'A-1', 'paid', 'web', '2026-11-07 09:00:00'],
            [2, 1, 'A-2', 'paid', 'shop', '2026-11-06 10:00:00'],
            [3, 1, 'A-3', 'pending', 'web', '2026-11-06 11:00:00'],
            [4, 1, 'A-4', null, null, '2026-11-01 08:00:00'],
            [5, 1, 'A-5', 'pending', 'web', '2026-10-31 23:59:59'],
            [6, 2, 'B-1', 'paid', 'web', '2026-11-07 08:00:00'],
        ];

        foreach ($rows as [$id, $team, $reference, $status, $channel, $createdAt]) {
            Order::create(['id' => $id, 'team_id' => $team, 'reference' => $reference, 'status' => $status, 'channel' => $channel, 'price' => '10.00', 'quantity' => 1, 'gift' => false, 'placed_at' => $createdAt, 'created_at' => $createdAt]);
        }
    }
}
