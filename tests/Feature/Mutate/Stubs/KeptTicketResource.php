<?php

namespace Lomkit\Rest\Tests\Feature\Mutate\Stubs;

use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Fields\MutationOperation;
use Lomkit\Rest\Relations\BelongsTo;
use Lomkit\Rest\Relations\BelongsToMany;
use Lomkit\Rest\Relations\MorphToMany;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TicketResource;

class KeptTicketResource extends TicketResource
{
    #[\Override]
    public function relations(): array
    {
        return [
            BelongsTo::make('team', KeptTeamResource::class)->withoutOperations(MutationOperation::Detach),
            BelongsToMany::make('tags', KeptTagResource::class)
                ->pivotFields([Integer::make('position')->nullable()])
                ->withoutOperations(MutationOperation::Detach),
            MorphToMany::make('labels', KeptTagResource::class)
                ->pivotFields([Integer::make('position')->nullable()])
                ->withoutOperations(MutationOperation::Update),
        ];
    }
}
