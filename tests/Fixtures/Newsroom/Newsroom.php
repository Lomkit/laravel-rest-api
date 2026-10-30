<?php

namespace Lomkit\Rest\Tests\Fixtures\Newsroom;

use Lomkit\Rest\Tests\Fixtures\Fixture;

class Newsroom implements Fixture
{
    #[\Override]
    public function migrations(): string
    {
        return __DIR__.'/migrations';
    }

    #[\Override]
    public function routes(): void {}
}
