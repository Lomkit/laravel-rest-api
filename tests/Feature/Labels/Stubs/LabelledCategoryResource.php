<?php

namespace Lomkit\Rest\Tests\Feature\Labels\Stubs;

use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Relations\BelongsToMany;
use Lomkit\Rest\Relations\HasMany;
use Lomkit\Rest\Tests\Fixtures\Catalog\CategoryResource;

class LabelledCategoryResource extends CategoryResource
{
    #[\Override]
    public function fields(): array
    {
        return [ID::make('id'), Text::make('name')->label('category name')];
    }

    #[\Override]
    public function relations(): array
    {
        return [
            HasMany::make('products', LabelledProductResource::class),
            BelongsToMany::make('featuredProducts', LabelledProductResource::class)->pivotFields([
                Integer::make('price')
                    ->label('featured price')
                    ->rules('min:1')
                    ->messages(['min' => 'Set the :attribute above zero.']),
            ]),
        ];
    }
}
