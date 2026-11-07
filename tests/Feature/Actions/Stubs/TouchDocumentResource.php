<?php

namespace Lomkit\Rest\Tests\Feature\Actions\Stubs;

class TouchDocumentResource extends StaffDocumentResource
{
    #[\Override]
    public function actions(): array
    {
        return [TouchDocuments::make(), QueuedTouchDocuments::make(), BatchedTouchDocuments::make()];
    }
}
