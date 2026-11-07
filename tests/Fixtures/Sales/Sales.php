<?php

namespace Lomkit\Rest\Tests\Fixtures\Sales;

use Lomkit\Rest\Tests\Fixtures\Fixture;

class Sales implements Fixture
{
    #[\Override]
    public function migrations(): string
    {
        return __DIR__.'/migrations';
    }

    #[\Override]
    public function routes(): void {}
}
