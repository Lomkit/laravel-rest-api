<?php

namespace Lomkit\Rest\Tests\Feature\Details\Stubs;

use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Relations\MorphTo;
use Lomkit\Rest\Tests\Fixtures\Gallery\ArtistResource;
use Lomkit\Rest\Tests\Fixtures\Gallery\ArtworkResource;

class MisdeclaredMorphArtworkResource extends ArtworkResource
{
    #[\Override]
    public function relations(RestRequest $request): array
    {
        return [MorphTo::make('artist', ArtistResource::class)];
    }
}
