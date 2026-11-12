<?php

namespace Lomkit\Rest\Tests\Feature\Mutate\Stubs;

use Lomkit\Rest\Relations\ChildRemoval;
use Lomkit\Rest\Relations\MorphMany;
use Lomkit\Rest\Tests\Fixtures\Library\BookResource;

class PerimeterSyncingBookResource extends BookResource
{
    #[\Override]
    public function relations(): array
    {
        return [MorphMany::make('labels', PerimeterLabelResource::class)->syncable(ChildRemoval::Detach)];
    }
}
