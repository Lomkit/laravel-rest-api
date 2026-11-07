<?php

namespace Lomkit\Rest\Tests\Feature\Events\Stubs;

use ArrayObject;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Mutations\MutationNode;
use Lomkit\Rest\Relations\BelongsTo;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TicketPolicy;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TicketResource;

class HookTicketResource extends TicketResource
{
    #[\Override]
    public function relations(): array
    {
        return [BelongsTo::make('team', HookTeamResource::class)];
    }

    #[\Override]
    public function mutatingAll(array $entries): void
    {
        EventLog::$payloads[] = new ArrayObject($entries);
    }

    #[\Override]
    public function perimeterQuery(Builder $query): Builder
    {
        if ($this->request()->user()?->getAuthIdentifier() === TicketPolicy::LEAD) {
            return $query;
        }

        return $query->where('tickets.status', 'open');
    }

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

    #[\Override]
    public function destroying(Model $model): void
    {
        HookLog::row($this, 'destroying', $model);
    }

    #[\Override]
    public function destroyed(Model $model): void
    {
        HookLog::row($this, 'destroyed', $model);
    }

    #[\Override]
    public function performDelete(Model $model): void
    {
        HookLog::row($this, 'performDelete', $model);

        parent::performDelete($model);
    }
}
