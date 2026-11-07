<?php

namespace Lomkit\Rest\Tests\Feature\Actions\Stubs;

class CheckedCloseOpenTickets extends CloseOpenTickets
{
    #[\Override]
    public function authorizeEach(): ?string
    {
        return 'update';
    }
}
