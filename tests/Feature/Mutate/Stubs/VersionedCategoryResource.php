<?php

namespace Lomkit\Rest\Tests\Feature\Mutate\Stubs;

use Lomkit\Rest\Fields\DateTime;
use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Relations\HasMany;
use Lomkit\Rest\Tests\Fixtures\Catalog\Category;

class VersionedCategoryResource extends Resource
{
    public static string $model = Category::class;

    #[\Override]
    public function fields(): array
    {
        return [ID::make('id'), Text::make('name'), DateTime::make('updated_at')->readonly()];
    }

    #[\Override]
    public function relations(): array
    {
        return [HasMany::make('products', VersionedProductResource::class)];
    }

    #[\Override]
    public function versioned(): bool
    {
        return true;
    }
}
