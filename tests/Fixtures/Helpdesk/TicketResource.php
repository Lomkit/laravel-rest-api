<?php

namespace Lomkit\Rest\Tests\Fixtures\Helpdesk;

use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Relations\BelongsTo;
use Lomkit\Rest\Relations\BelongsToMany;
use Lomkit\Rest\Relations\MorphToMany;

class TicketResource extends Resource
{
    public static string $model = Ticket::class;

    #[\Override]
    public function fields(RestRequest $request): array
    {
        return [ID::make('id'), Integer::make('team_id'), Text::make('title'), Text::make('status')];
    }

    #[\Override]
    public function relations(RestRequest $request): array
    {
        return [
            BelongsTo::make('team', TeamResource::class),
            BelongsToMany::make('tags', TagResource::class)->pivotFields([Integer::make('position')->nullable()]),
            MorphToMany::make('labels', TagResource::class)->pivotFields([Integer::make('position')->nullable()]),
        ];
    }

    #[\Override]
    public function actions(RestRequest $request): array
    {
        return [CloseTickets::make(), RefreshTickets::make(), ImportTickets::make(), ReportOutage::make(), WatchTickets::make()];
    }
}
