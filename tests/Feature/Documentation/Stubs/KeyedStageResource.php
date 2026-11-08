<?php

namespace Lomkit\Rest\Tests\Feature\Documentation\Stubs;

use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Tests\Fixtures\Festival\StageResource;

class KeyedStageResource extends StageResource
{
    public static string $keyType = 'string';

    #[\Override]
    public function fields(): array
    {
        return [ID::make('code')->keyType(static::$keyType), Text::make('name')];
    }
}
