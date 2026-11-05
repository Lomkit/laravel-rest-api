<?php

namespace Lomkit\Rest\Tests\Feature\Search\Stubs;

use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TicketResource;

class TitledTicketResource extends TicketResource
{
    #[\Override]
    public function fields(): array
    {
        return [ID::make('id'), Text::make('title')];
    }
}
