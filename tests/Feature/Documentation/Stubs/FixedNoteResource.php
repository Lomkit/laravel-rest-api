<?php

namespace Lomkit\Rest\Tests\Feature\Documentation\Stubs;

use Lomkit\Rest\Fields\MutationOperation;
use Lomkit\Rest\Relations\MorphTo;
use Lomkit\Rest\Tests\Fixtures\Festival\NoteResource;
use Lomkit\Rest\Tests\Fixtures\Festival\ShowResource;
use Lomkit\Rest\Tests\Fixtures\Festival\StageResource;

class FixedNoteResource extends NoteResource
{
    #[\Override]
    public function relations(): array
    {
        return [
            MorphTo::make('notable', [ShowResource::class, StageResource::class])->withoutOperations(MutationOperation::Create, MutationOperation::Update, MutationOperation::Attach, MutationOperation::Detach),
        ];
    }
}
