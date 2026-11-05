<?php

namespace Lomkit\Rest\Tests\Feature\Validation\Stubs;

use Lomkit\Rest\Tests\Fixtures\Publishing\PostResource;

class HighlightingPostResource extends PostResource
{
    #[\Override]
    public function actions(): array
    {
        return [HighlightPostsAction::make()];
    }
}
