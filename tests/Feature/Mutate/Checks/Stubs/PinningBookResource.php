<?php

namespace Lomkit\Rest\Tests\Feature\Mutate\Checks\Stubs;

use Lomkit\Rest\Relations\BelongsToMany;
use Lomkit\Rest\Tests\Fixtures\Library\BookResource;
use Lomkit\Rest\Tests\Fixtures\Library\ShelfResource;

class PinningBookResource extends BookResource
{
    #[\Override]
    public function relations(): array
    {
        return [BelongsToMany::make('shelves', ShelfResource::class)->abilities(attach: 'pinShelf')];
    }
}
