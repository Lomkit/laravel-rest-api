<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Tests\Fixtures\Catalog\OpenCatalogPolicy;

class PriceCapPolicy extends OpenCatalogPolicy
{
    #[\Override]
    public function view(?Authenticatable $user, Model $model): bool
    {
        return $model->getAttribute('price') < 100;
    }
}
