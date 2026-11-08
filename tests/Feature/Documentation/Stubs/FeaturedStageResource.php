<?php

namespace Lomkit\Rest\Tests\Feature\Documentation\Stubs;

use Lomkit\Rest\Tests\Fixtures\Festival\OnlyFeatured;
use Lomkit\Rest\Tests\Fixtures\Festival\StageResource;

class FeaturedStageResource extends StageResource
{
    #[\Override]
    public function instructions(): array
    {
        return [OnlyFeatured::make()];
    }
}
