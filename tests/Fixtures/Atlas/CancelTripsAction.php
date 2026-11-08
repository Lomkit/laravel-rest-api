<?php

namespace Lomkit\Rest\Tests\Fixtures\Atlas;

use Lomkit\Rest\Actions\Action;

class CancelTripsAction extends Action
{
    #[\Override]
    public function authorizeEach(): ?string
    {
        return 'update';
    }
}
