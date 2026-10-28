<?php

namespace Lomkit\Rest\Tests\Fixtures\Catalog;

use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;

class StaffProductResource extends Resource
{
    public static $model = Product::class;

    public function fields(RestRequest $request): array
    {
        if ($request->user()?->is_staff) {
            return ['id', 'name', 'price'];
        }

        return ['id', 'name'];
    }
}
