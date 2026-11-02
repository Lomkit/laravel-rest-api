<?php

namespace Lomkit\Rest\Tests\Feature\Mutate\Stubs;

use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Relations\BelongsToMany;
use Lomkit\Rest\Relations\HasMany;
use Lomkit\Rest\Tests\Fixtures\Catalog\CategoryResource;
use Lomkit\Rest\Tests\Fixtures\Catalog\ProductResource;

class ConstrainedCategoryResource extends CategoryResource
{
    #[\Override]
    public function relations(RestRequest $request): array
    {
        return [
            HasMany::make('products', ProductResource::class)->requiredOnCreation(),
            BelongsToMany::make('featuredProducts', ProductResource::class)->requiredOnCreation(),
        ];
    }
}
