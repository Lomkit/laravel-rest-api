<?php

namespace Lomkit\Rest\Tests\Unit\Fields\Stubs;

use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Relations\BelongsToMany;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TagResource;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TicketResource;

class LinkedTicketResource extends TicketResource
{
    public static string $model = LinkedTicket::class;

    #[\Override]
    public function relations(RestRequest $request): array
    {
        return [BelongsToMany::make('tags', TagResource::class)->pivotFields([Integer::make('position')->nullable()])];
    }
}
