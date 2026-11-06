<?php

namespace Lomkit\Rest\Tests\Feature\Search\Stubs\Paths;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Relations\BelongsToMany;
use Lomkit\Rest\Relations\HasMany;
use Lomkit\Rest\Tests\Fixtures\Publishing\AuthorResource;

class PathAuthorResource extends AuthorResource
{
    #[\Override]
    public function relations(): array
    {
        return [
            HasMany::make('posts', PathPostResource::class),
            BelongsToMany::make('tags', PathTagResource::class),
        ];
    }

    #[\Override]
    public function perimeterQuery(RestRequest $request, Builder $query): Builder
    {
        return $query->where('last_name', '!=', 'Hidden');
    }
}
