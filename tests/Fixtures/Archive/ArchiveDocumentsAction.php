<?php

namespace Lomkit\Rest\Tests\Fixtures\Archive;

use Illuminate\Support\Collection;
use Lomkit\Rest\Actions\Action;

class ArchiveDocumentsAction extends Action
{
    /**
     * @param  array<string, mixed>  $fields
     * @param  Collection<int, Document>  $models
     */
    public function handle(array $fields, Collection $models): void
    {
        foreach ($models as $document) {
            $document->update(['archived' => true]);
        }
    }
}
