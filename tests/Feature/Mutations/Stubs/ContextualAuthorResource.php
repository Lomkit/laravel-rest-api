<?php

namespace Lomkit\Rest\Tests\Feature\Mutations\Stubs;

use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Relations\HasMany;
use Lomkit\Rest\Tests\Fixtures\Publishing\AuthorResource;

class ContextualAuthorResource extends AuthorResource
{
    #[\Override]
    public function relations(RestRequest $request): array
    {
        return [HasMany::make('posts', ContextualPostResource::class)];
    }
}
