<?php

namespace Lomkit\Rest\Tests\Feature\Labels\Stubs;

use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Relations\BelongsTo;
use Lomkit\Rest\Tests\Fixtures\Catalog\ProductResource;

class LabelledProductResource extends ProductResource
{
    #[\Override]
    public function fields(RestRequest $request): array
    {
        return [
            ID::make('id'),
            Integer::make('category_id'),
            Text::make('name')
                ->label('product name')
                ->messages(['string' => 'The :attribute must be text.']),
            Integer::make('price')
                ->label(__('unit price'))
                ->rules('min:1')
                ->messages(['min' => 'Give the :attribute a positive value.']),
        ];
    }

    #[\Override]
    public function relations(RestRequest $request): array
    {
        return [
            BelongsTo::make('category', LabelledCategoryResource::class),
        ];
    }
}
