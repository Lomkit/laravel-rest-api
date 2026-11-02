<?php

namespace Lomkit\Rest\Tests\Feature\Search\Stubs;

use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Tests\Fixtures\Publishing\PostResource;

class QualifiedOrderPostResource extends PostResource
{
    #[\Override]
    public function defaultOrderBy(RestRequest $request): array
    {
        return ['posts.views' => 'asc'];
    }
}
