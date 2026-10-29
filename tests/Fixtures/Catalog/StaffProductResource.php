<?php

namespace Lomkit\Rest\Tests\Fixtures\Catalog;

use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;

class StaffProductResource extends Resource
{
    public static string $model = Product::class;

    public function fields(RestRequest $request): array
    {
        if ($request->user()?->is_staff) {
            return [ID::make('id'), Text::make('name'), Integer::make('price')];
        }

        return [ID::make('id'), Text::make('name')];
    }
}
