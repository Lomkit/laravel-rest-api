<?php

namespace Lomkit\Rest\Tests\Fixtures\Festival;

use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Relations\MorphTo;

class NoteResource extends Resource
{
    public static string $model = Note::class;

    #[\Override]
    public function fields(): array
    {
        return [ID::make('id'), Text::make('body')];
    }

    #[\Override]
    public function relations(): array
    {
        return [MorphTo::make('notable', [ShowResource::class, StageResource::class])];
    }
}
