<?php

namespace Lomkit\Rest\Tests\Feature\Search\Stubs\Pinboard;

use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Relations\MorphTo;
use Lomkit\Rest\Tests\Fixtures\Publishing\DraftResource;

class PinResource extends Resource
{
    public static string $model = Pin::class;

    #[\Override]
    public function fields(): array
    {
        return [ID::make('id'), Text::make('note')];
    }

    #[\Override]
    public function relations(): array
    {
        return [MorphTo::make('pinnable', DraftResource::class)];
    }
}
