<?php

namespace Lomkit\Rest\Tests\Feature\Search\Stubs;

use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Relations\HasMany;
use Lomkit\Rest\Relations\HasOneThrough;
use Lomkit\Rest\Tests\Fixtures\Newsroom\Desk;

class NewsDeskResource extends Resource
{
    public static string $model = Desk::class;

    #[\Override]
    public function fields(RestRequest $request): array
    {
        return [ID::make('id'), Text::make('name')];
    }

    #[\Override]
    public function relations(RestRequest $request): array
    {
        return [
            HasMany::make('stories', NewsStoryResource::class),
            HasOneThrough::make('leadSummary', NewsSummaryResource::class),
        ];
    }
}
