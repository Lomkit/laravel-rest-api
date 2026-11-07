<?php

namespace Lomkit\Rest\Tests\Feature\Search\Stubs\Illustrated;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Relations\BelongsTo;
use Lomkit\Rest\Tests\Fixtures\Newsroom\Comment;

class StoryCommentResource extends Resource
{
    public static string $model = Comment::class;

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

    #[\Override]
    public function perimeterQuery(Builder $query): Builder
    {
        return $query->where('comments.body', '!=', 'spam');
    }
}
