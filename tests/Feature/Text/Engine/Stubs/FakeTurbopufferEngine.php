<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine\Stubs;

use Laravel\Scout\Engines\TurbopufferEngine;

class FakeTurbopufferEngine extends TurbopufferEngine
{
    public function __construct() {}
}
