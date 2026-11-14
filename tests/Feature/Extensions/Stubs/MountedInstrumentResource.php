<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Lomkit\Rest\Tests\Fixtures\Lab\InstrumentResource;

class MountedInstrumentResource extends InstrumentResource
{
    public static string $model = MountedInstrument::class;

    #[\Override]
    public function relations(): array
    {
        return [];
    }
}
