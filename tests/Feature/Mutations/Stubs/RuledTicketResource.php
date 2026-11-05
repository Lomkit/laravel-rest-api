<?php

namespace Lomkit\Rest\Tests\Feature\Mutations\Stubs;

use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Relations\MorphToMany;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TicketResource;

class RuledTicketResource extends TicketResource
{
    #[\Override]
    public function relations(): array
    {
        return [MorphToMany::make('labels', RuledTagResource::class)->pivotFields([Integer::make('position')->nullable()])];
    }

    #[\Override]
    public function updateRules(): array
    {
        return [
            'title' => 'max:20',
        ];
    }
}
