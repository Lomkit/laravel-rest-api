<?php

namespace Lomkit\Rest\Tests\Feature\Authorization\Stubs;

class TrashableLeadTicketResource extends LeadTicketResource
{
    public static string $model = TrashableTicket::class;
}
