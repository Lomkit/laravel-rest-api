<?php

namespace Lomkit\Rest\Tests\Feature\Mutations\Stubs;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Http\Requests\MutateRequest;
use Lomkit\Rest\Tests\Fixtures\Catalog\Category;
use Lomkit\Rest\Tests\Fixtures\Catalog\ProductResource;

class ClearanceProductResource extends ProductResource
{
    #[\Override]
    public function mutating(MutateRequest $request, array $requestBody, Model $model): void
    {
        $model->category()->associate(Category::query()->where('name', 'Clearance')->sole());
    }
}
