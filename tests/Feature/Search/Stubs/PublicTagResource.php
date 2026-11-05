<?php

namespace Lomkit\Rest\Tests\Feature\Search\Stubs;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Relations\MorphedByMany;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TagResource;

class PublicTagResource extends TagResource
{
    #[\Override]
    public function searchQuery(RestRequest $request, Builder $query): Builder
    {
        return $query->where('tags.name', '!=', 'Secret');
    }

    #[\Override]
    public function relations(): array
    {
        return [MorphedByMany::make('labelledTickets', OpenTicketResource::class)];
    }
}
