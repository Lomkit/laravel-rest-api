<?php

namespace Lomkit\Rest\Tests\Feature\Mutate\Stubs;

use Lomkit\Rest\Relations\BelongsToMany;
use Lomkit\Rest\Relations\HasMany;
use Lomkit\Rest\Tests\Fixtures\Publishing\AuthorResource;
use Lomkit\Rest\Tests\Fixtures\Publishing\DraftResource;
use Lomkit\Rest\Tests\Fixtures\Publishing\PostResource;
use Lomkit\Rest\Tests\Fixtures\Publishing\TagResource;

class LinkCheckedAuthorResource extends AuthorResource
{
    #[\Override]
    public function relations(): array
    {
        return [
            HasMany::make('posts', PostResource::class)->abilities(attach: 'attachPost', detach: 'detachPost'),
            HasMany::make('drafts', DraftResource::class),
            BelongsToMany::make('tags', TagResource::class),
        ];
    }
}
