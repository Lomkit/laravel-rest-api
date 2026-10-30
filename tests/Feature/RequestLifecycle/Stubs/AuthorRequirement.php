<?php

namespace Lomkit\Rest\Tests\Feature\RequestLifecycle\Stubs;

use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;

class AuthorRequirement
{
    public static function onCreation(RestRequest $request, Resource $resource): bool
    {
        return true;
    }

    public function onUpdate(RestRequest $request, Resource $resource): bool
    {
        return false;
    }
}
