<?php

namespace Lomkit\Rest\Tests\Feature\Mutate\Stubs;

use Lomkit\Rest\Tests\Fixtures\Catalog\Product;

class TimestamplessProduct extends Product
{
    public $timestamps = false;
}
