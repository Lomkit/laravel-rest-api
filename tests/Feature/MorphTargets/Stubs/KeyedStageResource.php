<?php

namespace Lomkit\Rest\Tests\Feature\MorphTargets\Stubs;

use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Tests\Fixtures\Festival\StageResource;

class KeyedStageResource extends StageResource
{
    #[\Override]
    public function fields(): array
    {
        return [ID::make('code')->creatable(), Text::make('name')];
    }
}
