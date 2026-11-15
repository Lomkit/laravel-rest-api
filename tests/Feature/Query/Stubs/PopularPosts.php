<?php

namespace Lomkit\Rest\Tests\Feature\Query\Stubs;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Relations\HasMany;

final class PopularPosts extends HasMany
{
    /** @param Builder<Model> $query */
    #[\Override]
    public function applyPerimeter(Builder $query): void
    {
        parent::applyPerimeter($query);

        $query->orWhere('views', '>', 1000);
    }
}
