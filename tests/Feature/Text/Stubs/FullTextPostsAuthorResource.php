<?php

namespace Lomkit\Rest\Tests\Feature\Text\Stubs;

use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Relations\HasMany;

class FullTextPostsAuthorResource extends ContainsAuthorResource
{
    #[\Override]
    public function relations(RestRequest $request): array
    {
        return [HasMany::make('posts', FullTextPostResource::class)];
    }
}
