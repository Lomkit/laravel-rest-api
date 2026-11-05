<?php

namespace Lomkit\Rest\Tests\Feature\Mutate\Stubs;

use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Tests\Fixtures\Library\BookResource;
use Lomkit\Rest\Tests\Fixtures\Library\LabelResource;
use Lomkit\Rest\Tests\Fixtures\Library\ShelfResource;

class SyncedLabelResource extends LabelResource
{
    #[\Override]
    public function relations(RestRequest $request): array
    {
        return [SyncedMorphTo::make('labelable', [BookResource::class, ShelfResource::class])];
    }
}
