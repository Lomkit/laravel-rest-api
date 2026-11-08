<?php

namespace Lomkit\Rest\Tests\Feature\Search\Stubs\Festival;

use Lomkit\Rest\Fields\Boolean;
use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Relations\MorphMany;
use Lomkit\Rest\Relations\MorphOne;
use Lomkit\Rest\Relations\MorphToMany;

class ShowResource extends Resource
{
    public static string $model = Show::class;

    #[\Override]
    public function fields(): array
    {
        return [ID::make('id'), Text::make('title'), Boolean::make('featured')];
    }

    #[\Override]
    public function relations(): array
    {
        return [
            MorphMany::make('notes', NoteResource::class),
            MorphOne::make('note', NoteResource::class),
            MorphToMany::make('tags', TagResource::class),
        ];
    }
}
