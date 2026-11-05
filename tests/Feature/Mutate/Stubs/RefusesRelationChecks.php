<?php

namespace Lomkit\Rest\Tests\Feature\Mutate\Stubs;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Tests\Feature\Mutate\Characterization\Stubs\TracingPolicy;

trait RefusesRelationChecks
{
    #[\Override]
    public function authorizeToAttach(Model $model, Model $toAttachModel): void
    {
        throw new AuthorizationException('The resource refuses to attach '.TracingPolicy::name($toAttachModel).' to '.TracingPolicy::name($model).'.');
    }

    #[\Override]
    public function authorizeToDetach(Model $model, Model $toAttachModel): void
    {
        throw new AuthorizationException('The resource refuses to detach '.TracingPolicy::name($toAttachModel).' from '.TracingPolicy::name($model).'.');
    }
}
