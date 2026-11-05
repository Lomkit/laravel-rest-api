<?php

namespace Lomkit\Rest\Tests\Feature\Search\Stubs;

use Lomkit\Rest\Relations\HasOne;
use Lomkit\Rest\Tests\Fixtures\Publishing\AuthorResource;
use Lomkit\Rest\Tests\Fixtures\Publishing\DraftResource;
use Lomkit\Rest\Tests\Fixtures\Publishing\PostResource;

class LeadingAuthorResource extends AuthorResource
{
    public static string $model = LeadingAuthor::class;

    #[\Override]
    public function relations(): array
    {
        return [
            HasOne::make('draft', DraftResource::class),
            HasOne::make('leadPost', PostResource::class),
        ];
    }
}
