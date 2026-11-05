<?php

namespace Lomkit\Rest\Tests\Feature\Scramble\Stubs;

use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Tests\Fixtures\Publishing\TagResource;

class ImmutableTagResource extends TagResource
{
    #[\Override]
    public function fields(): array
    {
        return [ID::make('id'), Text::make('name')->updatable(false)];
    }
}
