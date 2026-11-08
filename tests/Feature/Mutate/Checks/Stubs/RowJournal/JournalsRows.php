<?php

namespace Lomkit\Rest\Tests\Feature\Mutate\Checks\Stubs\RowJournal;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Mutations\MutationNode;

trait JournalsRows
{
    #[\Override]
    public function mutated(MutationNode $entry, Model $model): void
    {
        parent::mutated($entry, $model);

        JournalEntry::write($entry, $model);
    }
}
