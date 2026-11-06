<?php

namespace Lomkit\Rest\Tests\Feature\Search\Stubs;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Relations\BelongsTo;
use Lomkit\Rest\Relations\BelongsToMany;
use Lomkit\Rest\Relations\MorphToMany;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TicketResource;

class OpenTicketResource extends TicketResource
{
    #[\Override]
    public function perimeterQuery(RestRequest $request, Builder $query): Builder
    {
        return $query->where('tickets.status', '!=', 'closed');
    }

    #[\Override]
    public function relations(): array
    {
        return [
            BelongsTo::make('team', OpenTeamResource::class),
            BelongsToMany::make('tags', PublicTagResource::class),
            MorphToMany::make('labels', PublicTagResource::class),
        ];
    }

    #[\Override]
    public function limits(): array
    {
        return [1, 10, 25, 50];
    }
}
