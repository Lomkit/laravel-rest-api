<?php

namespace Lomkit\Rest\Tests\Feature\Mutations\Stubs;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Mutations\MutationNode;
use Lomkit\Rest\Tests\Fixtures\Catalog\Category;
use Lomkit\Rest\Tests\Fixtures\Catalog\ProductResource;

class ClearanceProductResource extends ProductResource
{
    #[\Override]
    public function mutating(MutationNode $entry, Model $model): void
    {
        $model->category()->associate(Category::query()->where('name', 'Clearance')->sole());
    }
}
