<?php

namespace Lomkit\Rest\Tests\Fixtures\Catalog;

use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;

class CategoryResource extends Resource
{
    public static $model = Category::class;

    public function fields(RestRequest $request): array
    {
        return ['id', 'name'];
    }
}
