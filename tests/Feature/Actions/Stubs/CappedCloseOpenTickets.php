<?php

namespace Lomkit\Rest\Tests\Feature\Actions\Stubs;

class CappedCloseOpenTickets extends CloseOpenTickets
{
    public int $maxResources = 5;
}
