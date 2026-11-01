<?php

namespace Lomkit\Rest\Tests\Fixtures\Catalog;

use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Relations\BelongsTo;

class ProductResource extends Resource
{
    public static string $model = Product::class;

    #[\Override]
    public function fields(RestRequest $request): array
    {
        return [ID::make('id'), Integer::make('category_id'), Text::make('name'), Integer::make('price')];
    }

    #[\Override]
    public function relations(RestRequest $request): array
    {
        return [
            BelongsTo::make('category', CategoryResource::class),
        ];
    }

    #[\Override]
    public function instructions(RestRequest $request): array
    {
        return [PricedAt::make(), PricedBetween::make()];
    }
}
