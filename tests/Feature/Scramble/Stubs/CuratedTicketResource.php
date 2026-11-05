<?php

namespace Lomkit\Rest\Tests\Feature\Scramble\Stubs;

use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TagResource;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TicketResource;

class CuratedTicketResource extends TicketResource
{
    #[\Override]
    public function relations(): array
    {
        return [CuratedTags::make('tags', TagResource::class)->pivotFields([Integer::make('position')->nullable()])];
    }
}
