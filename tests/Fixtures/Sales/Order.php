<?php

namespace Lomkit\Rest\Tests\Fixtures\Sales;

use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;

#[Unguarded]
#[UsePolicy(OrderPolicy::class)]
class Order extends Model
{
    /** @return array<string, string> */
    #[\Override]
    protected function casts(): array
    {
        return ['status' => OrderStatus::class, 'gift' => 'boolean', 'placed_at' => 'datetime'];
    }
}
