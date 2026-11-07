<?php

namespace Lomkit\Rest\Tests\Feature\Search\Stubs\Paths;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Lomkit\Rest\Tests\Fixtures\Library\ShelfResource;

class PathShelfResource extends ShelfResource
{
    #[\Override]
    public function perimeterQuery(Builder $query): Builder
    {
        return $query->where('name', '!=', 'Hidden');
    }
}
