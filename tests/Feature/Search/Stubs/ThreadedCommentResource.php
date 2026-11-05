<?php

namespace Lomkit\Rest\Tests\Feature\Search\Stubs;

use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Relations\BelongsTo;
use Lomkit\Rest\Relations\HasMany;
use Lomkit\Rest\Tests\Fixtures\Newsroom\Comment;

class ThreadedCommentResource extends Resource
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
        return [
            BelongsTo::make('parent', self::class),
            HasMany::make('replies', self::class),
        ];
    }
}
