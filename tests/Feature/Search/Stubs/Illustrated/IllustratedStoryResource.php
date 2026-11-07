<?php

namespace Lomkit\Rest\Tests\Feature\Search\Stubs\Illustrated;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Relations\BelongsTo;
use Lomkit\Rest\Relations\MorphMany;
use Lomkit\Rest\Relations\MorphOne;
use Lomkit\Rest\Relations\MorphOneOfMany;

class IllustratedStoryResource extends Resource
{
    public static string $model = IllustratedStory::class;

    #[\Override]
    public function fields(): array
    {
        return [ID::make('id'), Text::make('headline')];
    }

    #[\Override]
    public function relations(): array
    {
        return [
            BelongsTo::make('desk', LeadDeskResource::class),
            MorphOne::make('cover', KeywordedPhotoResource::class),
            MorphMany::make('photos', KeywordedPhotoResource::class),
            MorphOneOfMany::make('latestPhoto', KeywordedPhotoResource::class),
        ];
    }

    #[\Override]
    public function perimeterQuery(Builder $query): Builder
    {
        return $query->where('stories.headline', '!=', 'Hidden');
    }
}
