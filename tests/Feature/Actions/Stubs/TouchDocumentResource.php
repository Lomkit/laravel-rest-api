<?php

namespace Lomkit\Rest\Tests\Feature\Actions\Stubs;

use Lomkit\Rest\Actions\ActionTarget;

class TouchDocumentResource extends StaffDocumentResource
{
    public static ActionTarget $target = ActionTarget::Search;

    #[\Override]
    public function actions(): array
    {
        return [
            TouchDocuments::make()->target(self::$target),
            QueuedTouchDocuments::make()
                ->destructive()
                ->confirm('Touch every document?'),
            BatchedTouchDocuments::make(),
        ];
    }
}
