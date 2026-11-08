<?php

namespace Lomkit\Rest\Tests\Feature\Actions\Stubs;

use Lomkit\Rest\Tests\Feature\Deletion\Stubs\SecondaryDocumentResource;

class RenamedSecondaryDocumentResource extends SecondaryDocumentResource
{
    #[\Override]
    public function actions(): array
    {
        return [
            RenameSecondaryDocuments::make(),
            AtomicRenameSecondaryDocuments::make()->atomic(),
            TransactionlessRenameSecondaryDocuments::make()->withoutTransaction(),
            QueuedRenameSecondaryDocuments::make(),
            QueuedTransactionlessRenameSecondaryDocuments::make(),
        ];
    }
}
