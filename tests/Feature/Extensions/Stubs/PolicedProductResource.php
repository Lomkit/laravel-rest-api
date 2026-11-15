<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Lomkit\Rest\Tests\Fixtures\Catalog\ProductResource;

class PolicedProductResource extends ProductResource
{
    #[\Override]
    public function alwaysSelect(): array
    {
        return ['price'];
    }
}
