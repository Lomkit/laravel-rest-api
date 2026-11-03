<?php

namespace Lomkit\Rest\Tests\Feature\Mutate\Stubs;

use Lomkit\Rest\Tests\Fixtures\Library\LabelResource;

class UnlistedLabelResource extends LabelResource
{
    public int $includeLimit = 5;
}
