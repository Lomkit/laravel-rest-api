<?php

namespace Lomkit\Rest\Tests\Feature\Search\Stubs\Illustrated;

use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Relations\HasMany;
use Lomkit\Rest\Relations\HasManyThrough;
use Lomkit\Rest\Relations\HasOneThrough;

class LeadDeskResource extends Resource
{
    public static string $model = LeadDesk::class;

    #[\Override]
    public function fields(): array
    {
        return [ID::make('id'), Text::make('name')];
    }

    #[\Override]
    public function relations(): array
    {
        return [
            HasMany::make('stories', IllustratedStoryResource::class),
            HasOneThrough::make('leadSummary', StoredSummaryResource::class),
            HasManyThrough::make('comments', StoryCommentResource::class),
        ];
    }
}
