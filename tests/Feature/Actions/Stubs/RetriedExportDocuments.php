<?php

namespace Lomkit\Rest\Tests\Feature\Actions\Stubs;

use Illuminate\Queue\Attributes\Tries;
use Illuminate\Support\Collection;
use Lomkit\Rest\Actions\ActionFields;
use Lomkit\Rest\Tests\Fixtures\Archive\Document;

#[Tries(2)]
class RetriedExportDocuments extends ExportDocuments
{
    /** @param Collection<int, Document> $models */
    #[\Override]
    public function handle(ActionFields $fields, Collection $models): void
    {
        QueuedRuns::$runs[] = ['attempts' => $this->attempts(), 'keys' => $models->modelKeys()];

        if ($this->attempts() === 1) {
            $this->release(10);
        }
    }
}
