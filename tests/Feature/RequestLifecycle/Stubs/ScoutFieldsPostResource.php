<?php

namespace Lomkit\Rest\Tests\Feature\RequestLifecycle\Stubs;

use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Tests\Fixtures\Publishing\PostResource;

class ScoutFieldsPostResource extends PostResource
{
    /** @return list<string> */
    public function scoutFields(RestRequest $request): array
    {
        return ['title'];
    }
}
