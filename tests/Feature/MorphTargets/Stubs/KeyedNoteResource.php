<?php

namespace Lomkit\Rest\Tests\Feature\MorphTargets\Stubs;

use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Relations\MorphTo;
use Lomkit\Rest\Tests\Fixtures\Festival\NoteResource;

class KeyedNoteResource extends NoteResource
{
    #[\Override]
    public function fields(): array
    {
        return [ID::make('id')->creatable(), Text::make('body')];
    }

    #[\Override]
    public function relations(): array
    {
        return [MorphTo::make('notable', [KeyedShowResource::class, KeyedStageResource::class])];
    }
}
