<?php

namespace Lomkit\Rest\Tests\Feature\Events\Stubs;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Mutations\MutationNode;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TeamResource;

class HookTeamResource extends TeamResource
{
    #[\Override]
    public function mutating(MutationNode $entry, Model $model): void
    {
        HookLog::entry($this, 'mutating', $entry, $model);
    }

    #[\Override]
    public function mutated(MutationNode $entry, Model $model): void
    {
        HookLog::entry($this, 'mutated', $entry, $model);
    }
}
