<?php

namespace Lomkit\Rest\Tests\Feature\Search\Stubs;

use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TicketResource;

class TitledTicketResource extends TicketResource
{
    #[\Override]
    public function fields(RestRequest $request): array
    {
        return [ID::make('id'), Text::make('title')];
    }
}
