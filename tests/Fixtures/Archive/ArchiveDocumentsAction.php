<?php

namespace Lomkit\Rest\Tests\Fixtures\Archive;

use Illuminate\Support\Collection;
use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Actions\ActionFields;

class ArchiveDocumentsAction extends Action
{
    /** @param Collection<int, Document> $models */
    #[\Override]
    public function handle(ActionFields $fields, Collection $models): void
    {
        foreach ($models as $document) {
            $document->update(['archived' => true]);
        }
    }
}
