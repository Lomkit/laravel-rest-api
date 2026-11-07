<?php

namespace Lomkit\Rest\Tests\Feature\Search\Stubs\Paths;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Lomkit\Rest\Relations\BelongsToMany;
use Lomkit\Rest\Tests\Fixtures\Library\BookResource;

class PathBookResource extends BookResource
{
    #[\Override]
    public function relations(): array
    {
        return [BelongsToMany::make('shelves', PathShelfResource::class)];
    }

    #[\Override]
    public function perimeterQuery(Builder $query): Builder
    {
        return $query->where('title', '!=', 'Hidden');
    }
}
