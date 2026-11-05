<?php

namespace Lomkit\Rest\Tests\Feature\Mutate\Stubs;

use Lomkit\Rest\Tests\Fixtures\Library\BookResource;

class RefusingBookResource extends BookResource
{
    use RefusesRelationChecks;
}
