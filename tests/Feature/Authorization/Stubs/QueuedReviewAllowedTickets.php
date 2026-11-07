<?php

namespace Lomkit\Rest\Tests\Feature\Authorization\Stubs;

class QueuedReviewAllowedTickets extends QueuedReviewTickets
{
    public bool $skipUnauthorized = true;
}
