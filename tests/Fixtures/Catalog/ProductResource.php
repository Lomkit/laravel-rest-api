<?php

namespace Lomkit\Rest\Tests\Fixtures\Catalog;

use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Relations\BelongsTo;

class ProductResource extends Resource
{
    public static $model = Product::class;

    public function fields(RestRequest $request): array
    {
        return ['id', 'category_id', 'name', 'price'];
    }

    public function relations(RestRequest $request): array
    {
        return [
            BelongsTo::make('category', CategoryResource::class),
        ];
    }
}
