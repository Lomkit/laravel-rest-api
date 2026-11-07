<?php

namespace Lomkit\Rest\Tests\Feature\Events\Stubs;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Tests\Fixtures\Archive\DocumentResource;

class HookDocumentResource extends DocumentResource
{
    #[\Override]
    public function restoring(Model $model): void
    {
        HookLog::row($this, 'restoring', $model);
    }

    #[\Override]
    public function restored(Model $model): void
    {
        HookLog::row($this, 'restored', $model);
    }

    #[\Override]
    public function forceDestroying(Model $model): void
    {
        HookLog::row($this, 'forceDestroying', $model);
    }

    #[\Override]
    public function forceDestroyed(Model $model): void
    {
        HookLog::row($this, 'forceDestroyed', $model);
    }

    #[\Override]
    public function performRestore(Model $model): void
    {
        HookLog::row($this, 'performRestore', $model);

        parent::performRestore($model);
    }

    #[\Override]
    public function performForceDelete(Model $model): void
    {
        HookLog::row($this, 'performForceDelete', $model);

        parent::performForceDelete($model);
    }
}
