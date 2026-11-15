<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Lomkit\Rest\Actions\ActionFields;
use Lomkit\Rest\Confirmation\Confirmation;

final class ConfirmedArchiveDocuments extends ArchiveDocumentsByOne
{
    #[\Override]
    public function confirmation(ActionFields $fields, int $rows): ?Confirmation
    {
        return WrittenDocumentResource::$confirming ? Confirmation::make('Archive the documents?') : null;
    }
}
