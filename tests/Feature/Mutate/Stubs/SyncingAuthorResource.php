<?php

namespace Lomkit\Rest\Tests\Feature\Mutate\Stubs;

use Lomkit\Rest\Relations\HasMany;
use Lomkit\Rest\Tests\Fixtures\Publishing\AuthorResource;
use Lomkit\Rest\Tests\Fixtures\Publishing\DraftResource;

class SyncingAuthorResource extends AuthorResource
{
    #[\Override]
    public function relations(): array
    {
        return [
            HasMany::make('drafts', DraftResource::class)
                ->deletable()
                ->syncable(),
        ];
    }
}
