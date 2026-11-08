<?php

namespace Lomkit\Rest\Tests\Fixtures\Festival;

use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Relations\BelongsTo;

class CommentResource extends Resource
{
    public static string $model = Comment::class;

    #[\Override]
    public function fields(): array
    {
        return [ID::make('id'), Integer::make('show_id')->readonly(), Integer::make('rating')->rules('between:1,5'), Text::make('body')];
    }

    #[\Override]
    public function relations(): array
    {
        return [BelongsTo::make('show', ShowResource::class)];
    }
}
