<?php

namespace Lomkit\Rest\Tests\Feature\Transactions\Stubs;

use Illuminate\Database\Eloquent\Collection;
use Lomkit\Rest\Tests\Feature\Deletion\Stubs\SecondaryDocumentResource;

class RetriedDocumentResource extends SecondaryDocumentResource
{
    #[\Override]
    public function actions(): array
    {
        return [
            RenameRetriedDocuments::make(),
            AtomicRenameRetriedDocuments::make()->atomic(),
            QueuedRenameRetriedDocuments::make(),
            RenameRetriedDocumentsPerRow::make()->transactionPerRow(),
            QueuedRenameRetriedDocumentsPerRow::make()->transactionPerRow(),
        ];
    }

    #[\Override]
    public function mutatedAll(array $operations): void
    {
        Interruptions::run('mutatedAll');
    }

    #[\Override]
    public function destroyedAll(Collection $models): void
    {
        Interruptions::run('destroyedAll');
    }
}
