<?php

namespace Lomkit\Rest\Tests\Feature\Deletion\Stubs;

use Illuminate\Database\Eloquent\Model;

class ArchivingDocumentResource extends TracingDocumentResource
{
    #[\Override]
    public function performDelete(Model $model): void
    {
        $model->forceFill(['archived' => true])->save();

        parent::performDelete($model);
    }

    #[\Override]
    public function performRestore(Model $model): void
    {
        $model->forceFill(['archived' => true])->save();

        parent::performRestore($model);
    }

    #[\Override]
    public function performForceDelete(Model $model): void
    {
        $model->forceFill(['archived' => true])->save();

        parent::performForceDelete($model);
    }
}
