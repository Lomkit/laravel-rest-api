<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine\Stubs;

use Laravel\Scout\Engines\Algolia3Engine;

class FakeAlgolia3Engine extends Algolia3Engine
{
    public function __construct(SettingsClient $client)
    {
        $this->algolia = $client;
        $this->softDelete = false;
    }
}
