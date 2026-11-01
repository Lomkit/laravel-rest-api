<?php

namespace Lomkit\Rest\Tests\Feature\Validation\Stubs;

use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Tests\Fixtures\Publishing\PostResource;

class HighlightingPostResource extends PostResource
{
    #[\Override]
    public function actions(RestRequest $request): array
    {
        return [HighlightPostsAction::make()];
    }
}
