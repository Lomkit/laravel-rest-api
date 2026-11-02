<?php

namespace Lomkit\Rest\Tests\Feature\Mutate\Stubs;

use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Relations\HasMany;
use Lomkit\Rest\Tests\Fixtures\Publishing\AuthorResource;

class DraftOnlyAuthorResource extends AuthorResource
{
    #[\Override]
    public function relations(RestRequest $request): array
    {
        return [HasMany::make('posts', DraftOnlyPostResource::class)];
    }
}
