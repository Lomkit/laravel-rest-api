<?php

namespace Lomkit\Rest\Tests\Feature\Search\Stubs\Paths;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Lomkit\Rest\Relations\BelongsTo;
use Lomkit\Rest\Tests\Fixtures\Publishing\PostResource;

class PathPostResource extends PostResource
{
    public int $includeLimit = 1;

    #[\Override]
    public function limits(): array
    {
        return [1, 10, 50];
    }

    #[\Override]
    public function relations(): array
    {
        return [BelongsTo::make('author', PathAuthorResource::class)];
    }

    #[\Override]
    public function perimeterQuery(Builder $query): Builder
    {
        return $query->where('title', '!=', 'Hidden');
    }
}
