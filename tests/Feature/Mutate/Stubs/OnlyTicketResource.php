<?php

namespace Lomkit\Rest\Tests\Feature\Mutate\Stubs;

use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Fields\MutationOperation;
use Lomkit\Rest\Relations\BelongsTo;
use Lomkit\Rest\Relations\BelongsToMany;
use Lomkit\Rest\Relations\MorphToMany;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TicketResource;

class OnlyTicketResource extends TicketResource
{
    #[\Override]
    public function relations(): array
    {
        return [
            BelongsTo::make('team', KeptTeamResource::class)->onlyOperations(MutationOperation::Attach),
            BelongsToMany::make('tags', KeptTagResource::class)
                ->pivotFields([Integer::make('position')->nullable()])
                ->onlyOperations(MutationOperation::Attach, MutationOperation::Sync),
            MorphToMany::make('labels', KeptTagResource::class)
                ->pivotFields([Integer::make('position')->nullable()])
                ->onlyOperations(),
        ];
    }
}
