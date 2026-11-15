<?php

namespace Lomkit\Rest\Tests\Feature\Query\Stubs;

use Lomkit\Rest\Tests\Feature\Search\Stubs\Paths\PathAuthorResource;
use Lomkit\Rest\Tests\Feature\Search\Stubs\Paths\PathPostResource;

final class PopularAuthorResource extends PathAuthorResource
{
    #[\Override]
    public function relations(): array
    {
        return [PopularPosts::make('posts', PathPostResource::class)];
    }
}
