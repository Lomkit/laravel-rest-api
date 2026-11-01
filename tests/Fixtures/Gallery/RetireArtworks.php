<?php

namespace Lomkit\Rest\Tests\Fixtures\Gallery;

use Illuminate\Support\Collection;
use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Fields\Boolean;
use Lomkit\Rest\Http\Requests\RestRequest;

class RetireArtworks extends Action
{
    #[\Override]
    public function fields(RestRequest $request): array
    {
        return [Boolean::make('notify')->default(false)];
    }

    /**
     * @param  array<string, mixed>  $fields
     * @param  Collection<int, Artwork>  $models
     */
    #[\Override]
    public function handle(array $fields, Collection $models): void
    {
        foreach ($models as $artwork) {
            $artwork->exhibitions()->detach();
        }
    }
}
