<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Lomkit\Rest\Relations\BelongsToMany;
use Lomkit\Rest\Tests\Fixtures\Library\BookResource;

class CappedBookLimitsResource extends BookResource
{
    public int $defaultLimit = 25;

    public int $includeLimit = 10;

    public ?int $maxTotal = 60;

    #[\Override]
    public function limits(): array
    {
        return [1, 10, 25, 50];
    }

    #[\Override]
    public function relations(): array
    {
        return [BelongsToMany::make('shelves', LimitShelfResource::class)];
    }
}
