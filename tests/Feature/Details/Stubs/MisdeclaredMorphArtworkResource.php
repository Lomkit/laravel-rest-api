<?php

namespace Lomkit\Rest\Tests\Feature\Details\Stubs;

use Lomkit\Rest\Relations\MorphTo;
use Lomkit\Rest\Tests\Fixtures\Gallery\ArtistResource;
use Lomkit\Rest\Tests\Fixtures\Gallery\ArtworkResource;

class MisdeclaredMorphArtworkResource extends ArtworkResource
{
    #[\Override]
    public function relations(): array
    {
        return [MorphTo::make('artist', ArtistResource::class)];
    }
}
