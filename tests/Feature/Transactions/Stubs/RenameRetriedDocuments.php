<?php

namespace Lomkit\Rest\Tests\Feature\Transactions\Stubs;

use Illuminate\Support\Collection;
use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Actions\ActionFields;
use Lomkit\Rest\Tests\Feature\Deletion\Stubs\SecondaryDocument;

class RenameRetriedDocuments extends Action
{
    public int $chunkCount = 2;

    /** @param Collection<int, SecondaryDocument> $models */
    #[\Override]
    public function handle(ActionFields $fields, Collection $models): void
    {
        foreach ($models as $document) {
            $document->update(['title' => 'Renamed']);
        }

        Interruptions::run('handle '.$models->pluck('id')->implode(','));
    }
}
