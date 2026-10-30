<?php

namespace Lomkit\Rest\Tests\Fixtures\Publishing;

use Lomkit\Rest\Fields\Boolean;
use Lomkit\Rest\Fields\Date;
use Lomkit\Rest\Fields\DateTime;
use Lomkit\Rest\Fields\Enum;
use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Fields\Json;
use Lomkit\Rest\Fields\Number;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Relations\BelongsTo;
use Lomkit\Rest\Scopes\Scope;

class PostResource extends Resource
{
    public static string $model = Post::class;

    public function fields(RestRequest $request): array
    {
        return [
            ID::make('id'),
            Text::make('title'),
            Text::make('summary')->nullable(),
            Enum::make('status'),
            Boolean::make('featured'),
            Integer::make('views'),
            Number::make('rating')->nullable(),
            Date::make('published_on'),
            DateTime::make('published_at')->nullable(),
            Json::make('metadata')->nullable(),
        ];
    }

    public function relations(RestRequest $request): array
    {
        return [
            BelongsTo::make('author', AuthorResource::class),
        ];
    }

    public function scopes(RestRequest $request): array
    {
        return [
            Scope::make('publishedSince')->fields([Date::make('since')]),
            Scope::make('popular')->fields([Integer::make('min_views')->default(100)]),
            Scope::make('viewedBetween')->fields([Integer::make('min')->rules('min:1'), Integer::make('max')->nullable()]),
            Scope::make('featured')->fields([Boolean::make('featured')->nullable()->default(true)]),
            Scope::make('reaching')->fields([Enum::make('reach', Reach::class)]),
        ];
    }

    public function instructions(RestRequest $request): array
    {
        return [HighlightPosts::make()->meta(['group' => 'ranking']), InStatus::make()];
    }

    public function actions(RestRequest $request): array
    {
        return [
            PublishPosts::make()->meta(['color' => '#FFFFFF']),
            QueuedPublishPosts::make(),
            BatchedPublishPosts::make(),
            ChangeStatus::make(),
        ];
    }
}
