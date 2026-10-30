<?php

namespace Lomkit\Rest\Tests\Feature\Mutations\Stubs;

use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Relations\MorphedByMany;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TagResource;

class RuledTagResource extends TagResource
{
    #[\Override]
    public function relations(RestRequest $request): array
    {
        return [MorphedByMany::make('labelledTickets', RuledTicketResource::class)->pivotFields([Integer::make('position')->nullable()])];
    }

    #[\Override]
    public function updateRules(RestRequest $request): array
    {
        return [
            'name' => 'max:20',
        ];
    }
}
