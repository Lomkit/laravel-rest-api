<?php

namespace Lomkit\Rest\Tests\Feature\Actions\Stubs;

use Lomkit\Rest\Tests\Fixtures\Gallery\ArtworkResource;

class LoadedArtworkResource extends ArtworkResource
{
    #[\Override]
    public function instructions(): array
    {
        return [...parent::instructions(), MarkLoadedArtworks::make()];
    }

    #[\Override]
    public function actions(): array
    {
        return [RecordArtworks::make()];
    }
}
