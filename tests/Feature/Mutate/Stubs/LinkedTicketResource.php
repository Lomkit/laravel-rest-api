<?php

namespace Lomkit\Rest\Tests\Feature\Mutate\Stubs;

use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Fields\MutationOperation;
use Lomkit\Rest\Relations\MorphToMany;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TicketResource;

class LinkedTicketResource extends TicketResource
{
    #[\Override]
    public function relations(): array
    {
        return [
            LinkedTags::make('tags', KeptTagResource::class)->pivotFields([Integer::make('position')->nullable()]),
            MorphToMany::make('labels', NamedTagResource::class)
                ->pivotFields([Integer::make('position')->nullable()])
                ->withoutOperations(MutationOperation::Update),
        ];
    }
}
