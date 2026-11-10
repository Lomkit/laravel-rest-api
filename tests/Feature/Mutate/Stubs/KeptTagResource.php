<?php

namespace Lomkit\Rest\Tests\Feature\Mutate\Stubs;

use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Relations\MorphedByMany;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TagResource;

class KeptTagResource extends TagResource
{
    #[\Override]
    public function relations(): array
    {
        return [MorphedByMany::make('labelledTickets', KeptTicketResource::class)->pivotFields([Integer::make('position')->nullable()])];
    }
}
