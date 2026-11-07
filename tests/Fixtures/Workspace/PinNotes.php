<?php

namespace Lomkit\Rest\Tests\Fixtures\Workspace;

use Illuminate\Support\Collection;
use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Actions\ActionFields;

class PinNotes extends Action
{
    /** @param Collection<int, Note> $models */
    #[\Override]
    public function handle(ActionFields $fields, Collection $models): void
    {
        foreach ($models as $note) {
            $note->update(['pinned' => true]);
        }
    }
}
