<?php

namespace Lomkit\Rest\Tests\Feature\Mutate\Stubs;

use Lomkit\Rest\Tests\Fixtures\Library\LabelResource;

class RefusingLabelResource extends LabelResource
{
    use RefusesRelationChecks;
}
