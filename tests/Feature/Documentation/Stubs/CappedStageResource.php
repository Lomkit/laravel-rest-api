<?php

namespace Lomkit\Rest\Tests\Feature\Documentation\Stubs;

use Lomkit\Rest\Tests\Fixtures\Festival\StageResource;

class CappedStageResource extends StageResource
{
    public ?int $maxTotal = 100;
}
