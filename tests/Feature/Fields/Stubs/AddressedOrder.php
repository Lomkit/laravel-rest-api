<?php

namespace Lomkit\Rest\Tests\Feature\Fields\Stubs;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Lomkit\Rest\Tests\Fixtures\Sales\Order;

class AddressedOrder extends Order
{
    protected $table = 'orders';

    /** @return HasMany<self, $this> */
    public function teamOrders(): HasMany
    {
        return $this->hasMany(self::class, 'team_id', 'team_id');
    }
}
