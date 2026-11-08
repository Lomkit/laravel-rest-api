<?php

namespace Lomkit\Rest\Tests\Feature\Events\Stubs;

use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Fields\Text;

class AnnotatedCloseEventTickets extends CloseEventTickets
{
    #[\Override]
    public function fields(): array
    {
        return [
            Text::make('reason'),
            Integer::make('minutes')->default(30),
        ];
    }
}
