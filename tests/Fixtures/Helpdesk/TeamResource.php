<?php

namespace Lomkit\Rest\Tests\Fixtures\Helpdesk;

use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Relations\HasMany;
use Lomkit\Rest\Relations\HasManyThrough;

class TeamResource extends Resource
{
    public static string $model = Team::class;

    public function fields(RestRequest $request): array
    {
        return [ID::make('id'), Text::make('name')];
    }

    public function relations(RestRequest $request): array
    {
        return [HasMany::make('tickets', TicketResource::class), HasManyThrough::make('replies', ReplyResource::class)];
    }
}
