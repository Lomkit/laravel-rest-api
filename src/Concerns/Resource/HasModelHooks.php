<?php

namespace Lomkit\Rest\Concerns\Resource;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Http\Requests\MutateRequest;

trait HasModelHooks
{
    public function beforeMutating(MutateRequest $request, array $requestBody, Model $model): void
    {
        //
    }

    public function afterMutating(MutateRequest $request, array $requestBody, Model $model): void
    {
        //
    }
}
