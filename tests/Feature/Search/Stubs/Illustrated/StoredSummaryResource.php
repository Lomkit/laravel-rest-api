<?php

namespace Lomkit\Rest\Tests\Feature\Search\Stubs\Illustrated;

use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Relations\BelongsTo;

class StoredSummaryResource extends Resource
{
    public static string $model = StoredSummary::class;

    #[\Override]
    public function fields(): array
    {
        return [ID::make('id'), Text::make('body')];
    }

    #[\Override]
    public function relations(): array
    {
        return [BelongsTo::make('story', IllustratedStoryResource::class)];
    }
}
