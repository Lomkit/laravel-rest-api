<?php

namespace Lomkit\Rest\Tests\Fixtures\Festival;

use Illuminate\Bus\PendingBatch;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Collection;
use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Actions\ActionFields;
use Lomkit\Rest\Contracts\BatchableAction;

class ArchiveShows extends Action implements BatchableAction, ShouldQueue
{
    /** @param Collection<int, Show> $models */
    #[\Override]
    public function handle(ActionFields $fields, Collection $models): void
    {
        foreach ($models as $show) {
            $show->delete();
        }
    }

    #[\Override]
    public function withBatch(ActionFields $fields, PendingBatch $batch): void {}
}
