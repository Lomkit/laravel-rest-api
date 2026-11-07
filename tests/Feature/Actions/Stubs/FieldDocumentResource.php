<?php

namespace Lomkit\Rest\Tests\Feature\Actions\Stubs;

use Lomkit\Rest\Actions\ActionTarget;

class FieldDocumentResource extends StaffDocumentResource
{
    public static ActionTarget $target = ActionTarget::Search;

    #[\Override]
    public function actions(): array
    {
        return [
            ReadDocumentFields::make()->target(self::$target),
            QueuedReadDocumentFields::make()->target(self::$target),
            BatchedReadDocumentFields::make(),
            ChunkedReadDocumentFields::make(),
        ];
    }
}
