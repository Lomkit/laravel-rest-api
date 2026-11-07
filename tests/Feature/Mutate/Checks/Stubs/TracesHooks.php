<?php

namespace Lomkit\Rest\Tests\Feature\Mutate\Checks\Stubs;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Mutations\MutationNode;
use Lomkit\Rest\Tests\Feature\Mutate\Characterization\Stubs\TracingPolicy;

trait TracesHooks
{
    #[\Override]
    public function mutating(MutationNode $entry, Model $model): void
    {
        TracingPolicy::$trace[] = 'hook mutating '.ChecksPolicy::describe($model);
    }

    #[\Override]
    public function mutated(MutationNode $entry, Model $model): void
    {
        TracingPolicy::$trace[] = 'hook mutated '.ChecksPolicy::describe($model);
    }
}
