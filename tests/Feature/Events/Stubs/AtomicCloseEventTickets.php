<?php

namespace Lomkit\Rest\Tests\Feature\Events\Stubs;

class AtomicCloseEventTickets extends CloseEventTickets
{
    public bool $atomic = true;
}
