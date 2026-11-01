<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine\Stubs;

use Laravel\Scout\Engines\MeilisearchEngine;

class FakeMeilisearchEngine extends MeilisearchEngine
{
    public function __construct(SettingsClient $client)
    {
        $this->meilisearch = $client;
        $this->softDelete = false;
        $this->config = [];
    }
}
