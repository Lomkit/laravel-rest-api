<?php

namespace Lomkit\Rest\Tests\Feature\Authorization\Stubs;

use Lomkit\Rest\Relations\BelongsToMany;
use Lomkit\Rest\Relations\MorphOne;
use Lomkit\Rest\Tests\Fixtures\Library\BookResource;

class UngatedShelfBookResource extends BookResource
{
    #[\Override]
    public function relations(): array
    {
        return [
            BelongsToMany::make('shelves', UngatedShelfResource::class),
            MorphOne::make('label', ShelfLabelResource::class),
        ];
    }
}
