<?php

namespace Lomkit\Rest\Tests\Fixtures\Festival;

use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Relations\ChildRemoval;
use Lomkit\Rest\Relations\HasMany;
use Lomkit\Rest\Relations\HasManyThrough;

class StageResource extends Resource
{
    public static string $model = Stage::class;

    #[\Override]
    public function fields(): array
    {
        return [
            ID::make('code')
                ->creatable()
                ->examples('main'),
            Text::make('name')->examples('Main stage'),
        ];
    }

    #[\Override]
    public function relations(): array
    {
        return [
            HasMany::make('shows', ShowResource::class)
                ->deletable()
                ->syncable(ChildRemoval::Delete),
            HasManyThrough::make('comments', CommentResource::class),
        ];
    }

    #[\Override]
    public function defaultOrderBy(): array
    {
        return ['code' => 'asc'];
    }
}
