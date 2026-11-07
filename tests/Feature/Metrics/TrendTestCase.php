<?php

namespace Lomkit\Rest\Tests\Feature\Metrics;

use Lomkit\Rest\Tests\Fixtures\Sales\Order;

abstract class TrendTestCase extends SalesTestCase
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Order::query()->delete();

        $rows = [
            [11, 1, 'T-11', 'paid', 'web', '100.00', 1, '2026-01-15 10:00:00', '2026-01-16'],
            [12, 1, 'T-12', 'paid', 'shop', '10.00', 2, '2026-03-31 22:30:00', '2026-04-01'],
            [13, 1, 'T-13', 'refunded', 'web', '30.00', 3, '2026-05-31 23:30:00', null],
            [14, 1, 'T-14', 'pending', 'web', '20.00', 1, '2026-10-31 22:30:00', '2026-11-02'],
            [15, 1, 'T-15', 'paid', 'shop', '12.50', 1, '2026-11-01 05:30:00', '2026-11-03'],
            [16, 1, 'T-16', null, 'web', '5.00', 4, '2026-11-02 04:30:00', null],
            [17, 1, 'T-17', 'paid', null, '7.00', 1, '2026-11-06 23:30:00', '2026-11-08'],
            [18, 1, 'T-18', 'pending', 'web', '15.00', 2, '2026-11-07 11:00:00', '2026-11-07'],
            [19, 1, 'T-19', 'paid', 'web', '99.00', 9, '2026-11-07 12:00:01', '2026-11-09'],
            [20, 2, 'U-20', 'paid', 'web', '500.00', 5, '2026-11-05 10:00:00', '2026-11-05'],
        ];

        foreach ($rows as [$id, $team, $reference, $status, $channel, $price, $quantity, $placedAt, $deliveredOn]) {
            Order::create(['id' => $id, 'team_id' => $team, 'reference' => $reference, 'status' => $status, 'channel' => $channel, 'price' => $price, 'quantity' => $quantity, 'gift' => false, 'placed_at' => $placedAt, 'delivered_on' => $deliveredOn]);
        }
    }

    /** @param array<string, mixed> $data */
    protected static function trend(array $data): string
    {
        return (string) json_encode(['data' => ['type' => 'trend', ...$data]], JSON_PRESERVE_ZERO_FRACTION);
    }

    /**
     * @param  array<string, int|float|null>  $points
     * @return list<array{label: string, value: int|float|null}>
     */
    protected static function points(array $points): array
    {
        return array_map(fn (string $label, int|float|null $value): array => ['label' => $label, 'value' => $value], array_keys($points), array_values($points));
    }
}
