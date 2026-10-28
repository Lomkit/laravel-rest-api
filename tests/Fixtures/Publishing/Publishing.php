<?php

namespace Lomkit\Rest\Tests\Fixtures\Publishing;

use Lomkit\Rest\Tests\Fixtures\Fixture;

class Publishing implements Fixture
{
    public function migrations(): string
    {
        return __DIR__.'/migrations';
    }

    public function routes(): void {}
}
