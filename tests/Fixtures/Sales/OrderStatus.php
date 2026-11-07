<?php

namespace Lomkit\Rest\Tests\Fixtures\Sales;

enum OrderStatus: string
{
    case Paid = 'paid';
    case Pending = 'pending';
    case Refunded = 'refunded';
}
