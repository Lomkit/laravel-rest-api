<?php

namespace Lomkit\Rest\Tests\Feature\Metrics\Stubs;

use Lomkit\Rest\Relations\MorphTo;
use Lomkit\Rest\Tests\Fixtures\Library\LabelResource;
use Lomkit\Rest\Tests\Fixtures\Library\ShelfResource;

class MorphLabelResource extends LabelResource
{
    #[\Override]
    public function relations(): array
    {
        return [MorphTo::make('labelable', [SecretBookResource::class, ShelfResource::class])];
    }

    #[\Override]
    public function metrics(): array
    {
        return [
            RowCount::make()
                ->cacheFor(600)
                ->cacheScope('global'),
        ];
    }
}
