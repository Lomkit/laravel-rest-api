<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Lomkit\Rest\Actions\ActionTarget;
use Lomkit\Rest\Tests\Fixtures\Archive\ArchiveDocumentsAction;
use Lomkit\Rest\Tests\Fixtures\Archive\DocumentResource;

class KeyedDocumentResource extends DocumentResource
{
    #[\Override]
    public function actions(): array
    {
        return [ArchiveDocumentsAction::make()->target(ActionTarget::Resources)];
    }
}
