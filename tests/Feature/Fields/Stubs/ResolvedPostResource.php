<?php

namespace Lomkit\Rest\Tests\Feature\Fields\Stubs;

use Lomkit\Rest\Relations\BelongsTo;
use Lomkit\Rest\Tests\Fixtures\Publishing\PostResource;

class ResolvedPostResource extends PostResource
{
    #[\Override]
    public function relations(): array
    {
        return [BelongsTo::make('author', ResolvedAuthorResource::class)];
    }
}
