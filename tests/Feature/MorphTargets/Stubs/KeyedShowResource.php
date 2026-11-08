<?php

namespace Lomkit\Rest\Tests\Feature\MorphTargets\Stubs;

use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Tests\Fixtures\Festival\ShowResource;

class KeyedShowResource extends ShowResource
{
    #[\Override]
    public function fields(): array
    {
        return [ID::make('id')->creatable(), ...array_slice(parent::fields(), 1)];
    }
}
