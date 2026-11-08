<?php

namespace Lomkit\Rest\Tests\Feature\Search\Stubs\Festival;

use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Relations\MorphMany;

class StageResource extends Resource
{
    public static string $model = Stage::class;

    #[\Override]
    public function fields(): array
    {
        return [ID::make('code'), Text::make('name')];
    }

    #[\Override]
    public function relations(): array
    {
        return [MorphMany::make('notes', NoteResource::class)];
    }
}
