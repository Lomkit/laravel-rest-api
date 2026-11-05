<?php

namespace Lomkit\Rest\Tests\Feature\Search\Stubs;

use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Relations\BelongsTo;
use Lomkit\Rest\Relations\HasMany;
use Lomkit\Rest\Tests\Fixtures\Newsroom\Story;

class NewsStoryResource extends Resource
{
    public static string $model = Story::class;

    public int $defaultLimit = 10;

    public int $includeLimit = 1;

    #[\Override]
    public function fields(): array
    {
        return [ID::make('id'), Text::make('headline')];
    }

    #[\Override]
    public function relations(): array
    {
        return [
            BelongsTo::make('reporter', NewsReporterResource::class),
            HasMany::make('comments', ThreadedCommentResource::class),
        ];
    }

    #[\Override]
    public function limits(): array
    {
        return [1, 10];
    }
}
