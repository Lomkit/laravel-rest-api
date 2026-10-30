<?php

namespace Lomkit\Rest\Tests\Feature\Mutations\Stubs;

use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Relations\MorphToMany;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TicketResource;

class RuledTicketResource extends TicketResource
{
    #[\Override]
    public function relations(RestRequest $request): array
    {
        return [MorphToMany::make('labels', RuledTagResource::class)->withPivotFields(['position'])];
    }

    #[\Override]
    public function updateRules(RestRequest $request): array
    {
        return [
            'title' => 'max:20',
        ];
    }
}
