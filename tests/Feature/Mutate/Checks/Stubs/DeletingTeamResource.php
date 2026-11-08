<?php

namespace Lomkit\Rest\Tests\Feature\Mutate\Checks\Stubs;

use Lomkit\Rest\Mutations\MutationNode;
use Lomkit\Rest\Mutations\OperationEntry;
use Lomkit\Rest\Relations\HasMany;
use Lomkit\Rest\Tests\Feature\Mutate\Characterization\Stubs\TracingPolicy;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TeamResource;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TicketResource;

class DeletingTeamResource extends TeamResource
{
    use TracesHooks;

    #[\Override]
    public function relations(): array
    {
        return [HasMany::make('tickets', TicketResource::class)->deletable()];
    }

    /** @param list<MutationNode> $entries */
    #[\Override]
    public function mutatingAll(array $entries): void
    {
        $operations = [];

        foreach ($entries[0]->relations['tickets'] ?? [] as $entry) {
            $operations[] = $entry->operation->value.' '.json_encode($entry->key);
        }

        TracingPolicy::$trace[] = 'hook mutatingAll '.implode(', ', $operations);
    }

    /** @param list<OperationEntry> $operations */
    #[\Override]
    public function mutatedAll(array $operations): void
    {
        TracingPolicy::$trace[] = 'hook mutatedAll '.json_encode($operations);
    }
}
