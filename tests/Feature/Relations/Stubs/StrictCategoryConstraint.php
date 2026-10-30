<?php

namespace Lomkit\Rest\Tests\Feature\Relations\Stubs;

use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Tests\Fixtures\Catalog\CategoryResource;

class StrictCategoryConstraint
{
    public static function check(RestRequest $request, Resource $resource): bool
    {
        return $resource instanceof CategoryResource && $request->boolean('strict');
    }

    public function evaluate(RestRequest $request, Resource $resource): bool
    {
        return self::check($request, $resource);
    }

    public function __invoke(RestRequest $request, Resource $resource): bool
    {
        return self::check($request, $resource);
    }
}
