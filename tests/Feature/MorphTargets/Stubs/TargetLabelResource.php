<?php

namespace Lomkit\Rest\Tests\Feature\MorphTargets\Stubs;

use Lomkit\Rest\Relations\MorphTo;
use Lomkit\Rest\Tests\Fixtures\Library\BookResource;
use Lomkit\Rest\Tests\Fixtures\Library\LabelResource;
use Lomkit\Rest\Tests\Fixtures\Library\ShelfResource;

class TargetLabelResource extends LabelResource
{
    #[\Override]
    public function relations(): array
    {
        return [MorphTo::make('labelable', [BookResource::class, ShelfResource::class])->abilities(attach: 'attachLabelable', detach: 'detachLabelable')];
    }
}
