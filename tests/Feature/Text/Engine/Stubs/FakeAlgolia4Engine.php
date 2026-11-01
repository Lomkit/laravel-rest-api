<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine\Stubs;

use Laravel\Scout\Engines\Algolia4Engine;

class FakeAlgolia4Engine extends Algolia4Engine
{
    public function __construct(SettingsClient $client)
    {
        $this->algolia = $client;
        $this->softDelete = false;
    }
}
