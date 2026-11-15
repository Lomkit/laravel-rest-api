<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Relations\HasMany;
use Lomkit\Rest\Tests\Fixtures\Catalog\Category;

class ReadOnlyKeyCategoryResource extends Resource
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
            HasMany::make('products', ReadOnlyKeyProductResource::class),
        ];
    }
}
