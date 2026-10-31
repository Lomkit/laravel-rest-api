<?php

namespace Lomkit\Rest\Tests\Feature\Details\Stubs;

use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Relations\BelongsTo;
use Lomkit\Rest\Tests\Fixtures\Gallery\ArtistResource;
use Lomkit\Rest\Tests\Fixtures\Gallery\ArtworkResource;
use Lomkit\Rest\Tests\Fixtures\Gallery\ByMedium;
use Lomkit\Rest\Tests\Fixtures\Gallery\RetireArtworks;

class KeyedArtworkResource extends ArtworkResource
{
    #[\Override]
    public function actions(RestRequest $request): array
    {
        return ['retire' => RetireArtworks::make()];
    }

    #[\Override]
    public function instructions(RestRequest $request): array
    {
        return ['medium' => ByMedium::make()];
    }

    #[\Override]
    public function relations(RestRequest $request): array
    {
        return ['artist' => BelongsTo::make('artist', ArtistResource::class)];
    }
}
