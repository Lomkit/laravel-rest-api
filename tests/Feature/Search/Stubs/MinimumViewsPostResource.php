<?php

namespace Lomkit\Rest\Tests\Feature\Search\Stubs;

use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Tests\Fixtures\Publishing\PostResource;

class MinimumViewsPostResource extends PostResource
{
    #[\Override]
    public function instructions(RestRequest $request): array
    {
        return [MinimumViews::make()];
    }
}
