<?php

namespace Lomkit\Rest\Tests\Fixtures\Helpdesk;

use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Relations\BelongsTo;
use Lomkit\Rest\Relations\BelongsToMany;

class TicketResource extends Resource
{
    public static string $model = Ticket::class;

    public function fields(RestRequest $request): array
    {
        return [ID::make('id'), Integer::make('team_id'), Text::make('title'), Text::make('status')];
    }

    public function relations(RestRequest $request): array
    {
        return [BelongsTo::make('team', TeamResource::class), BelongsToMany::make('tags', TagResource::class)];
    }
}
