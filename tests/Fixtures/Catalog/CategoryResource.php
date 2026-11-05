<?php

namespace Lomkit\Rest\Tests\Fixtures\Catalog;

use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Relations\BelongsToMany;
use Lomkit\Rest\Relations\HasMany;

class CategoryResource extends Resource
{
    public static string $model = Category::class;

    #[\Override]
    public function fields(): array
    {
        return [ID::make('id'), Text::make('name')];
    }

    #[\Override]
    public function relations(): array
    {
        return [
            HasMany::make('products', ProductResource::class),
            BelongsToMany::make('featuredProducts', ProductResource::class),
        ];
    }
}
