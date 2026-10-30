<?php

namespace Lomkit\Rest\Tests\Feature\Relations\Stubs;

use Lomkit\Rest\Fields\Enum;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Relations\BelongsToMany;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TagResource;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TicketResource;

class AssignedTicketResource extends TicketResource
{
    public static string $model = AssignedTicket::class;

    #[\Override]
    public function relations(RestRequest $request): array
    {
        return [BelongsToMany::make('tags', TagResource::class)->pivotFields([Enum::make('weight')])];
    }
}
