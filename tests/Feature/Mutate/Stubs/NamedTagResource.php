<?php

namespace Lomkit\Rest\Tests\Feature\Mutate\Stubs;

use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Text;

class NamedTagResource extends KeptTagResource
{
    #[\Override]
    public function fields(): array
    {
        return [ID::make('id'), Text::make('name')->rules('required')];
    }
}
