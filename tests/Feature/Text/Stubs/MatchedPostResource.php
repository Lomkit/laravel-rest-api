<?php

namespace Lomkit\Rest\Tests\Feature\Text\Stubs;

use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Tests\Fixtures\Publishing\PostResource;

class MatchedPostResource extends PostResource
{
    #[\Override]
    public function instructions(RestRequest $request): array
    {
        return [MatchingTitle::make()];
    }
}
