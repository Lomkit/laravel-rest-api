<?php

namespace Lomkit\Rest\Tests\Fixtures\Workspace;

use Illuminate\Support\Collection;
use Lomkit\Rest\Actions\Action;

class PinNamedNotes extends Action
{
    public bool $targeted = true;

    public int $maxResources = 3;

    /**
     * @param  array<string, mixed>  $fields
     * @param  Collection<int, Note>  $models
     */
    #[\Override]
    public function handle(array $fields, Collection $models): void
    {
        foreach ($models as $note) {
            $note->update(['pinned' => true]);
        }
    }
}
