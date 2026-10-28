<?php

namespace Lomkit\Rest\Concerns\Resource;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Http\Requests\DestroyRequest;
use Lomkit\Rest\Http\Requests\ForceDestroyRequest;
use Lomkit\Rest\Http\Requests\MutateRequest;
use Lomkit\Rest\Http\Requests\RestoreRequest;

trait HasResourceHooks
{
    /**
     * Executed when a model has been "mutating".
     */
    public function mutating(MutateRequest $request, array $requestBody, Model $model): void
    {
        //
    }

    /**
     * Executed when a model has been "mutated".
     */
    public function mutated(MutateRequest $request, array $requestBody, Model $model): void
    {
        //
    }

    /**
     * Executed when a model has been "destroying".
     */
    public function destroying(DestroyRequest $request, Model $model): void
    {
        //
    }

    /**
     * Executed when a model has been "destroyed".
     */
    public function destroyed(DestroyRequest $request, Model $model): void
    {
        //
    }

    /**
     * Executed when a model has been "restoring".
     */
    public function restoring(RestoreRequest $request, Model $model): void
    {
        //
    }

    /**
     * Executed when a model has been "restored".
     */
    public function restored(RestoreRequest $request, Model $model): void
    {
        //
    }

    /**
     * Executed when a model has been "forceDestroying".
     */
    public function forceDestroying(ForceDestroyRequest $request, Model $model): void
    {
        //
    }

    /**
     * Executed when a model has been "forceDestroyed".
     */
    public function forceDestroyed(ForceDestroyRequest $request, Model $model): void
    {
        //
    }
}
