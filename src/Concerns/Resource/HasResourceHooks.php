<?php

namespace Lomkit\Rest\Concerns\Resource;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Http\Requests\DestroyRequest;
use Lomkit\Rest\Http\Requests\MutateRequest;

trait HasResourceHooks
{
    // @TODO: PHP DOC
    // @TODO: separate functioning by types ? beforeCreating, beforeUpdating, etc ? seems more logical
    public function beforeMutating(MutateRequest $request, array $requestBody, Model $model): void
    {
        //
    }

    public function afterMutating(MutateRequest $request, array $requestBody, Model $model): void
    {
        //
    }

    public function beforeDestroying(DestroyRequest $request, Model $model): void
    {
        //
    }

    public function afterDestroying(DestroyRequest $request, Model $model): void
    {
        //
    }

    public function beforeRestoring(MutateRequest $request, Model $model): void
    {
        //
    }

    public function afterRestoring(MutateRequest $request, Model $model): void
    {
        //
    }

    public function beforeForceDestroying(MutateRequest $request, Model $model): void
    {
        //
    }

    public function afterForceDestroying(MutateRequest $request, Model $model): void
    {
        //
    }
}
