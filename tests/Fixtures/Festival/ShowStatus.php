<?php

namespace Lomkit\Rest\Tests\Fixtures\Festival;

enum ShowStatus: string
{
    case Planned = 'planned';
    case OnSale = 'on_sale';
    case SoldOut = 'sold_out';
}
