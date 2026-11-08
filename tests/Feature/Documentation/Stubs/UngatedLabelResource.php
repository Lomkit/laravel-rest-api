<?php

namespace Lomkit\Rest\Tests\Feature\Documentation\Stubs;

use Lomkit\Rest\Tests\Fixtures\Festival\LabelResource;

class UngatedLabelResource extends LabelResource
{
    #[\Override]
    public function isGatingEnabled(): bool
    {
        return false;
    }
}
