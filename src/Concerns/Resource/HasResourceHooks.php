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
     *
     * @param MutateRequest $request
     * @param array $requestBody
     * @param Model $model
     * @return void
     */
    public function mutating(MutateRequest $request, array $requestBody, Model $model): void
    {
        //
    }

    /**
     * Executed when a model has been "mutated".
     *
     * @param MutateRequest $request
     * @param array $requestBody
     * @param Model $model
     * @return void
     */
    public function mutated(MutateRequest $request, array $requestBody, Model $model): void
    {
        //
    }

    /**
     * Executed when a model has been "destroying".
     *
     * @param DestroyRequest $request
     * @param Model $model
     * @return void
     */
    public function destroying(DestroyRequest $request, Model $model): void
    {
        //
    }

    /**
     * Executed when a model has been "destroyed".
     *
     * @param DestroyRequest $request
     * @param Model $model
     * @return void
     */
    public function destroyed(DestroyRequest $request, Model $model): void
    {
        //
    }

    /**
     * Executed when a model has been "restoring".
     *
     * @param RestoreRequest $request
     * @param Model $model
     * @return void
     */
    public function restoring(RestoreRequest $request, Model $model): void
    {
        //
    }

    /**
     * Executed when a model has been "restored".
     *
     * @param RestoreRequest $request
     * @param Model $model
     * @return void
     */
    public function restored(RestoreRequest $request, Model $model): void
    {
        //
    }

    /**
     * Executed when a model has been "forceDestroying".
     *
     * @param ForceDestroyRequest $request
     * @param Model $model
     * @return void
     */
    public function forceDestroying(ForceDestroyRequest $request, Model $model): void
    {
        //
    }

    /**
     * Executed when a model has been "forceDestroyed".
     *
     * @param ForceDestroyRequest $request
     * @param Model $model
     * @return void
     */
    public function forceDestroyed(ForceDestroyRequest $request, Model $model): void
    {
        //
    }
}
