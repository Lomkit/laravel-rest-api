<?php

namespace Lomkit\Rest\Tests\Feature\Actions\Stubs;

use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Tests\Feature\Deletion\Stubs\SecondaryDocumentResource;

class RowRenamedSecondaryDocumentResource extends SecondaryDocumentResource
{
    public static ?Action $misdeclared = null;

    #[\Override]
    public function actions(): array
    {
        if (self::$misdeclared !== null) {
            return [self::$misdeclared];
        }

        return [
            RenameSecondaryDocumentsPerRow::make()->transactionPerRow(),
            QueuedRenameSecondaryDocumentsPerRow::make(),
        ];
    }
}
