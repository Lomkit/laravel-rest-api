<?php

namespace Lomkit\Rest\Tests\Feature\Labels\Stubs;

use Lomkit\Rest\Relations\HasMany;
use Lomkit\Rest\Tests\Fixtures\Publishing\AuthorResource;

class LabelledAuthorResource extends AuthorResource
{
    #[\Override]
    public function relations(): array
    {
        return [
            HasMany::make('posts', LabelledPostResource::class),
        ];
    }
}
