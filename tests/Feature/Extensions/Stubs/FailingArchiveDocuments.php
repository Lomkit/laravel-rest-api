<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Illuminate\Support\Collection;
use Lomkit\Rest\Actions\ActionFields;
use Lomkit\Rest\Tests\Fixtures\Archive\Document;
use RuntimeException;

final class FailingArchiveDocuments extends ArchiveDocumentsByOne
{
    /** @param Collection<int, Document> $models */
    #[\Override]
    public function handle(ActionFields $fields, Collection $models): void
    {
        parent::handle($fields, $models);

        if ($models->contains('id', 2)) {
            throw new RuntimeException('The second document is locked.');
        }
    }
}
