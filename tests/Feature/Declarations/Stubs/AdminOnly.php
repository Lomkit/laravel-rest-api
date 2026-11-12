<?php

namespace Lomkit\Rest\Tests\Feature\Declarations\Stubs;

trait AdminOnly
{
    public array $middleware = ['can:admin'];
}
