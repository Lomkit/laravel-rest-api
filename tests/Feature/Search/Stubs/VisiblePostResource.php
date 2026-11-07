<?php

namespace Lomkit\Rest\Tests\Feature\Search\Stubs;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Lomkit\Rest\Tests\Fixtures\Publishing\PostResource;

class VisiblePostResource extends PostResource
{
    #[\Override]
    public function perimeterQuery(Builder $query): Builder
    {
        return $query->where('status', 'published')->orWhere('views', '>', 100);
    }
}
