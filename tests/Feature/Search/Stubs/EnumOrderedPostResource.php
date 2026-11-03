<?php

namespace Lomkit\Rest\Tests\Feature\Search\Stubs;

use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Tests\Fixtures\Publishing\PostResource;
use SortDirection;

class EnumOrderedPostResource extends PostResource
{
    /** @return array<string, SortDirection> */
    #[\Override]
    public function defaultOrderBy(RestRequest $request): array
    {
        return ['views' => SortDirection::Descending];
    }
}
