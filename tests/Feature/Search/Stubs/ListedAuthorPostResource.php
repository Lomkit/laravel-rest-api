<?php

namespace Lomkit\Rest\Tests\Feature\Search\Stubs;

use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Relations\BelongsTo;
use Lomkit\Rest\Tests\Fixtures\Publishing\PostResource;

class ListedAuthorPostResource extends PostResource
{
    #[\Override]
    public function relations(RestRequest $request): array
    {
        return [BelongsTo::make('author', ListedAuthorResource::class)];
    }
}
