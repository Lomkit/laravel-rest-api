<?php

namespace Lomkit\Rest\Tests\Fixtures\Helpdesk;

use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Relations\MorphedByMany;

class TagResource extends Resource
{
    public static string $model = Tag::class;

    #[\Override]
    public function fields(RestRequest $request): array
    {
        return [ID::make('id'), Text::make('name')];
    }

    #[\Override]
    public function relations(RestRequest $request): array
    {
        return [MorphedByMany::make('labelledTickets', TicketResource::class)->withPivotFields(['position'])];
    }
}
