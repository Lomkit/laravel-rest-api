<?php

namespace Lomkit\Rest\Tests\Feature\Mutate\Checks\Stubs;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Http\Requests\MutateRequest;
use Lomkit\Rest\Tests\Feature\Mutate\Characterization\Stubs\TracingPolicy;

trait TracesHooks
{
    #[\Override]
    public function mutating(MutateRequest $request, array $requestBody, Model $model): void
    {
        TracingPolicy::$trace[] = 'hook mutating '.ChecksPolicy::describe($model);
    }

    #[\Override]
    public function mutated(MutateRequest $request, array $requestBody, Model $model): void
    {
        TracingPolicy::$trace[] = 'hook mutated '.ChecksPolicy::describe($model);
    }
}
