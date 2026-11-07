<?php

namespace Lomkit\Rest\Tests\Fixtures\Gallery;

use Illuminate\Support\Collection;
use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Actions\ActionFields;
use Lomkit\Rest\Fields\Boolean;

class RetireArtworks extends Action
{
    #[\Override]
    public function fields(): array
    {
        return [Boolean::make('notify')->default(false)];
    }

    /** @param Collection<int, Artwork> $models */
    #[\Override]
    public function handle(ActionFields $fields, Collection $models): void
    {
        foreach ($models as $artwork) {
            $artwork->exhibitions()->detach();
        }
    }
}
