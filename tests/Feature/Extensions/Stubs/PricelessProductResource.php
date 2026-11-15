<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Relations\BelongsTo;
use Lomkit\Rest\Tests\Fixtures\Catalog\CategoryResource;
use Lomkit\Rest\Tests\Fixtures\Catalog\Product;

class PricelessProductResource extends Resource
{
    public static string $model = Product::class;

    #[\Override]
    public function fields(): array
    {
        return [ID::make('id'), Integer::make('category_id'), Text::make('name')];
    }

    #[\Override]
    public function relations(): array
    {
        return [
            BelongsTo::make('category', CategoryResource::class),
        ];
    }
}
