<?php

namespace Lomkit\Rest\Tests\Feature\Search\Stubs;

use Lomkit\Rest\Relations\HasMany;
use Lomkit\Rest\Tests\Fixtures\Publishing\AuthorResource;

class QualifiedOrderAuthorResource extends AuthorResource
{
    #[\Override]
    public function relations(): array
    {
        return [HasMany::make('posts', QualifiedOrderPostResource::class)];
    }
}
