<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Lomkit\Rest\Tests\Fixtures\Catalog\ProductResource;

class CountedFieldsProductResource extends ProductResource
{
    public static int $reads = 0;

    #[\Override]
    public function fields(): array
    {
        self::$reads++;

        return parent::fields();
    }
}
