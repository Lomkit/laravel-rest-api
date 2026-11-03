<?php

namespace Lomkit\Rest\Tests\Feature\Search\Stubs;

use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Relations\HasMany;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TeamResource;

class TitledTeamResource extends TeamResource
{
    #[\Override]
    public function relations(RestRequest $request): array
    {
        return [HasMany::make('tickets', TitledTicketResource::class)];
    }
}
