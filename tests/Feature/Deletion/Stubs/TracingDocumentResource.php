<?php

namespace Lomkit\Rest\Tests\Feature\Deletion\Stubs;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Tests\Fixtures\Archive\DocumentResource;

class TracingDocumentResource extends DocumentResource
{
    #[\Override]
    public function destroyingAll(Collection $models): void
    {
        DeletionTrace::recordAll('destroyingAll', $models);
    }

    #[\Override]
    public function destroyedAll(Collection $models): void
    {
        DeletionTrace::recordAll('destroyedAll', $models);
    }

    #[\Override]
    public function restoringAll(Collection $models): void
    {
        DeletionTrace::recordAll('restoringAll', $models);
    }

    #[\Override]
    public function restoredAll(Collection $models): void
    {
        DeletionTrace::recordAll('restoredAll', $models);
    }

    #[\Override]
    public function forceDestroyingAll(Collection $models): void
    {
        DeletionTrace::recordAll('forceDestroyingAll', $models);
    }

    #[\Override]
    public function forceDestroyedAll(Collection $models): void
    {
        DeletionTrace::recordAll('forceDestroyedAll', $models);
    }

    #[\Override]
    public function destroying(Model $model): void
    {
        DeletionTrace::record('destroying', $model);
    }

    #[\Override]
    public function destroyed(Model $model): void
    {
        DeletionTrace::record('destroyed', $model);
    }

    #[\Override]
    public function restoring(Model $model): void
    {
        DeletionTrace::record('restoring', $model);
    }

    #[\Override]
    public function restored(Model $model): void
    {
        DeletionTrace::record('restored', $model);
    }

    #[\Override]
    public function forceDestroying(Model $model): void
    {
        DeletionTrace::record('forceDestroying', $model);
    }

    #[\Override]
    public function forceDestroyed(Model $model): void
    {
        DeletionTrace::record('forceDestroyed', $model);
    }

    #[\Override]
    public function performDelete(Model $model): void
    {
        DeletionTrace::record('performDelete', $model);

        parent::performDelete($model);
    }

    #[\Override]
    public function performRestore(Model $model): void
    {
        DeletionTrace::record('performRestore', $model);

        parent::performRestore($model);
    }

    #[\Override]
    public function performForceDelete(Model $model): void
    {
        DeletionTrace::record('performForceDelete', $model);

        parent::performForceDelete($model);
    }
}
