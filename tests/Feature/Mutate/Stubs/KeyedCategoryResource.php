<?php

namespace Lomkit\Rest\Tests\Feature\Mutate\Stubs;

use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Tests\Fixtures\Catalog\CategoryResource;
use Lomkit\Rest\Tests\Fixtures\Catalog\ProductResource;

class KeyedCategoryResource extends CategoryResource
{
    #[\Override]
    public function relations(RestRequest $request): array
    {
        return [KeyedHasMany::make('products', ProductResource::class)];
    }
}
