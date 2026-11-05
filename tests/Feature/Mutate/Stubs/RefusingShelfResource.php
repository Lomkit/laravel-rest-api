<?php

namespace Lomkit\Rest\Tests\Feature\Mutate\Stubs;

use Lomkit\Rest\Tests\Fixtures\Library\ShelfResource;

class RefusingShelfResource extends ShelfResource
{
    use RefusesRelationChecks;
}
