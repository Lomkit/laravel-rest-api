<?php

namespace Lomkit\Rest\Tests\Feature\Search\Stubs;

use Lomkit\Rest\Tests\Fixtures\Publishing\PostResource;
use SortDirection;

class EnumOrderedPostResource extends PostResource
{
    /** @return array<string, SortDirection> */
    #[\Override]
    public function defaultOrderBy(): array
    {
        return ['views' => SortDirection::Descending];
    }
}
