<?php

namespace Lomkit\Rest\Tests\Feature\Search\Stubs;

use Lomkit\Rest\Tests\Fixtures\Catalog\ProductResource;

class BulkProductResource extends ProductResource
{
    #[\Override]
    public function limits(): array
    {
        return [10, 50, 300];
    }
}
