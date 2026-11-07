<?php

namespace Lomkit\Rest\Tests\Feature\Actions\Stubs;

use Illuminate\Support\Collection;
use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Actions\ActionFields;
use Lomkit\Rest\Tests\Feature\Deletion\Stubs\SecondaryDocument;
use RuntimeException;

class RenameSecondaryDocuments extends Action
{
    public int $chunkCount = 2;

    /** @param Collection<int, SecondaryDocument> $models */
    #[\Override]
    public function handle(ActionFields $fields, Collection $models): void
    {
        foreach ($models as $document) {
            if ($document->getAttribute('title') === 'Budget') {
                throw new RuntimeException('The budget cannot be renamed.');
            }

            $document->update(['title' => 'Renamed']);
        }
    }
}
