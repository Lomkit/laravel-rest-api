<?php

namespace Lomkit\Rest\Tests\Feature\Events\Stubs;

use Lomkit\Rest\Actions\ActionTarget;
use Lomkit\Rest\Tests\Feature\Deletion\Stubs\PerimeterDocumentResource;
use Lomkit\Rest\Tests\Fixtures\Archive\ArchiveDocumentsAction;

class ReachDocumentResource extends PerimeterDocumentResource
{
    #[\Override]
    public function actions(): array
    {
        return [ArchiveDocumentsAction::make()->target(ActionTarget::Resources)];
    }
}
