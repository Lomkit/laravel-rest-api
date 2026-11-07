<?php

namespace Lomkit\Rest\Tests\Feature\Deletion\Stubs;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Http\Requests\DestroyRequest;
use Lomkit\Rest\Http\Requests\ForceDestroyRequest;
use Lomkit\Rest\Http\Requests\RestoreRequest;
use Lomkit\Rest\Http\Requests\RestRequest;
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
    public function destroying(DestroyRequest $request, Model $model): void
    {
        DeletionTrace::record('destroying', $model);
    }

    #[\Override]
    public function destroyed(DestroyRequest $request, Model $model): void
    {
        DeletionTrace::record('destroyed', $model);
    }

    #[\Override]
    public function restoring(RestoreRequest $request, Model $model): void
    {
        DeletionTrace::record('restoring', $model);
    }

    #[\Override]
    public function restored(RestoreRequest $request, Model $model): void
    {
        DeletionTrace::record('restored', $model);
    }

    #[\Override]
    public function forceDestroying(ForceDestroyRequest $request, Model $model): void
    {
        DeletionTrace::record('forceDestroying', $model);
    }

    #[\Override]
    public function forceDestroyed(ForceDestroyRequest $request, Model $model): void
    {
        DeletionTrace::record('forceDestroyed', $model);
    }

    #[\Override]
    public function performDelete(RestRequest $request, Model $model): void
    {
        DeletionTrace::record('performDelete', $model);

        parent::performDelete($request, $model);
    }

    #[\Override]
    public function performRestore(RestRequest $request, Model $model): void
    {
        DeletionTrace::record('performRestore', $model);

        parent::performRestore($request, $model);
    }

    #[\Override]
    public function performForceDelete(RestRequest $request, Model $model): void
    {
        DeletionTrace::record('performForceDelete', $model);

        parent::performForceDelete($request, $model);
    }
}
