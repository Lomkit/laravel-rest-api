<?php

namespace Lomkit\Rest\Tests\Feature\Mutate\Stubs;

use Lomkit\Rest\Relations\MorphMany;
use Lomkit\Rest\Tests\Fixtures\Library\BookResource;

class UnlistedLabelsBookResource extends BookResource
{
    #[\Override]
    public function relations(): array
    {
        return [MorphMany::make('labels', UnlistedLabelResource::class)];
    }
}
