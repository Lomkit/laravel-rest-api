<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Lomkit\Rest\Relations\BelongsToMany;
use Lomkit\Rest\Relations\HasMany;
use Lomkit\Rest\Tests\Fixtures\Publishing\AuthorResource;
use Lomkit\Rest\Tests\Fixtures\Publishing\TagResource;

class RestrictedAuthorResource extends AuthorResource
{
    #[\Override]
    public function relations(): array
    {
        return [
            HasMany::make('posts', RestrictedPostResource::class),
            BelongsToMany::make('tags', TagResource::class),
        ];
    }
}
