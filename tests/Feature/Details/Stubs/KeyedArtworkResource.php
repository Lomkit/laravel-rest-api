<?php

namespace Lomkit\Rest\Tests\Feature\Details\Stubs;

use Lomkit\Rest\Relations\BelongsTo;
use Lomkit\Rest\Tests\Fixtures\Gallery\ArtistResource;
use Lomkit\Rest\Tests\Fixtures\Gallery\ArtworkResource;
use Lomkit\Rest\Tests\Fixtures\Gallery\ByMedium;
use Lomkit\Rest\Tests\Fixtures\Gallery\RetireArtworks;

class KeyedArtworkResource extends ArtworkResource
{
    #[\Override]
    public function actions(): array
    {
        return ['retire' => RetireArtworks::make()];
    }

    #[\Override]
    public function instructions(): array
    {
        return ['medium' => ByMedium::make()];
    }

    #[\Override]
    public function relations(): array
    {
        return ['artist' => BelongsTo::make('artist', ArtistResource::class)];
    }
}
