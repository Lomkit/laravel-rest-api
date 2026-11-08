<?php

namespace Lomkit\Rest\Tests\Feature\Documentation\Stubs;

use Lomkit\Rest\Tests\Fixtures\Festival\NoteResource;

class UngatedNoteResource extends NoteResource
{
    #[\Override]
    public function isGatingEnabled(): bool
    {
        return false;
    }
}
