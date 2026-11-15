<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Illuminate\Support\Collection;
use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Actions\ActionFields;
use Lomkit\Rest\Tests\Feature\Events\Stubs\EventLog;
use Lomkit\Rest\Tests\Fixtures\Archive\Document;

class ArchiveDocumentsByOne extends Action
{
    public int $chunkCount = 1;

    #[\Override]
    public function authorizeEach(): ?string
    {
        return 'update';
    }

    /** @param Collection<int, Document> $models */
    #[\Override]
    public function handle(ActionFields $fields, Collection $models): void
    {
        EventLog::record('handle '.$models->pluck('id')->implode(','));

        foreach ($models as $document) {
            $document->update(['archived' => true]);
        }
    }
}
