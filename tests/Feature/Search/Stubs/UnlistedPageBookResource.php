<?php

namespace Lomkit\Rest\Tests\Feature\Search\Stubs;

use Lomkit\Rest\Tests\Fixtures\Library\BookResource;

class UnlistedPageBookResource extends BookResource
{
    public int $defaultLimit = 32;
}
