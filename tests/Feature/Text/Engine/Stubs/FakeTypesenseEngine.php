<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine\Stubs;

use Laravel\Scout\Engines\TypesenseEngine;

class FakeTypesenseEngine extends TypesenseEngine
{
    public function __construct()
    {
        $this->maxTotalResults = 1000;
        $this->config = [];
    }
}
