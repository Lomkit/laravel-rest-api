<?php

namespace Lomkit\Rest\Tests\Feature\Events\Stubs;

use Lomkit\Rest\Relations\BelongsToMany;
use Lomkit\Rest\Relations\MorphMany;
use Lomkit\Rest\Tests\Feature\Mutate\Stubs\HidesNamedRows;
use Lomkit\Rest\Tests\Feature\Mutate\Stubs\PerimeterLabelResource;
use Lomkit\Rest\Tests\Feature\Mutate\Stubs\PerimeterShelfResource;
use Lomkit\Rest\Tests\Fixtures\Library\BookResource;

class ReachBookResource extends BookResource
{
    use HidesNamedRows;

    #[\Override]
    public function relations(): array
    {
        return [
            MorphMany::make('labels', PerimeterLabelResource::class)->deletable(),
            BelongsToMany::make('shelves', PerimeterShelfResource::class),
        ];
    }

    protected static function nameColumn(): string
    {
        return 'books.title';
    }
}
