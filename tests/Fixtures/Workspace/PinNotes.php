<?php

namespace Lomkit\Rest\Tests\Fixtures\Workspace;

use Illuminate\Support\Collection;
use Lomkit\Rest\Actions\Action;

class PinNotes extends Action
{
    /**
     * @param  array<string, mixed>  $fields
     * @param  Collection<int, Note>  $models
     */
    public function handle(array $fields, Collection $models): void
    {
        foreach ($models as $note) {
            $note->update(['pinned' => true]);
        }
    }
}
