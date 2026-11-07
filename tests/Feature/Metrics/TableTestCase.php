<?php

namespace Lomkit\Rest\Tests\Feature\Metrics;

use Lomkit\Rest\Tests\Fixtures\Sales\Order;

abstract class TableTestCase extends SalesTestCase
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Order::query()->delete();

        $rows = [
            [21, 1, 'C-21', 'paid', 'web', 'acme', '100.00', 2, 5, '2026-11-01 09:00:00'],
            [22, 1, 'C-22', 'paid', 'shop', 'acme', '50.00', 1, 4, '2026-10-20 10:00:00'],
            [23, 1, 'C-23', 'refunded', 'web', 'acme', '25.00', 1, null, '2026-09-01 10:00:00'],
            [24, 1, 'C-24', 'paid', 'web', 'bolt', '80.00', 4, 3, '2026-10-25 10:00:00'],
            [25, 1, 'C-25', 'pending', 'web', 'bolt', '40.00', 2, null, '2026-10-26 10:00:00'],
            [26, 1, 'C-26', 'paid', 'shop', 'crane', '120.00', 3, null, '2026-10-30 10:00:00'],
            [27, 1, 'C-27', 'paid', 'web', 'delta', '30.00', 1, 2, '2026-10-08 12:00:00'],
            [28, 1, 'C-28', null, 'web', 'delta', '30.00', 1, 4, '2026-10-08 11:59:59'],
            [29, 1, 'C-29', 'pending', 'shop', 'echo', '45.00', 1, 5, '2026-11-02 10:00:00'],
            [30, 1, 'C-30', 'paid', 'web', 'fox', '45.00', 2, null, '2026-11-03 10:00:00'],
            [31, 1, 'C-31', 'paid', null, null, '45.00', 1, 1, '2026-11-04 10:00:00'],
            [32, 2, 'D-32', 'paid', 'web', 'acme', '999.00', 9, 5, '2026-11-02 10:00:00'],
        ];

        foreach ($rows as [$id, $team, $reference, $status, $channel, $customer, $price, $quantity, $rating, $placedAt]) {
            Order::create(['id' => $id, 'team_id' => $team, 'reference' => $reference, 'status' => $status, 'channel' => $channel, 'customer' => $customer, 'price' => $price, 'quantity' => $quantity, 'rating' => $rating, 'gift' => false, 'placed_at' => $placedAt]);
        }
    }

    /**
     * @param  array<string, array{string, string}>  $columns
     * @param  list<array{int|string|bool|null, string|null, array<string, int|float|null>}>  $rows
     */
    protected static function table(array $columns, array $rows): string
    {
        $columns = array_map(fn (string $name, array $column): array => ['name' => $name, 'label' => $column[0], 'aggregate' => $column[1]], array_keys($columns), array_values($columns));
        $rows = array_map(fn (array $row): array => ['key' => $row[0], 'label' => $row[1], 'values' => $row[2]], $rows);

        return (string) json_encode(['data' => ['type' => 'table', 'columns' => $columns, 'rows' => $rows]], JSON_PRESERVE_ZERO_FRACTION);
    }
}
