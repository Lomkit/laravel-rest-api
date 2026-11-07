<?php

namespace Lomkit\Rest\Tests\Feature\Deletion\Stubs;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class FailingSecondaryDocumentResource extends SecondaryDocumentResource
{
    #[\Override]
    public function destroyed(Model $model): void
    {
        if ($model->getAttribute('title') === 'Budget') {
            throw new RuntimeException('The budget cannot be destroyed.');
        }
    }
}
