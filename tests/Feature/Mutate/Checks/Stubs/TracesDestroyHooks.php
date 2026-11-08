<?php

namespace Lomkit\Rest\Tests\Feature\Mutate\Checks\Stubs;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Tests\Feature\Mutate\Characterization\Stubs\TracingPolicy;

trait TracesDestroyHooks
{
    #[\Override]
    public function destroying(Model $model): void
    {
        TracingPolicy::$trace[] = 'hook destroying '.ChecksPolicy::describe($model);
    }

    #[\Override]
    public function destroyed(Model $model): void
    {
        TracingPolicy::$trace[] = 'hook destroyed '.ChecksPolicy::describe($model);
    }

    #[\Override]
    public function destroyingAll(Collection $models): void
    {
        TracingPolicy::$trace[] = 'hook destroyingAll';
    }

    #[\Override]
    public function destroyedAll(Collection $models): void
    {
        TracingPolicy::$trace[] = 'hook destroyedAll';
    }
}
