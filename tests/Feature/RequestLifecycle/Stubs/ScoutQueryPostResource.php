<?php

namespace Lomkit\Rest\Tests\Feature\RequestLifecycle\Stubs;

use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Tests\Fixtures\Publishing\PostResource;

class ScoutQueryPostResource extends PostResource
{
    public function searchScoutQuery(RestRequest $request, object $query): object
    {
        return $query;
    }
}
