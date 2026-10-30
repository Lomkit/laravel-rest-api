<?php

namespace Lomkit\Rest\Tests\Fixtures\Accounts;

use Lomkit\Rest\Tests\Fixtures\Fixture;

class Accounts implements Fixture
{
    #[\Override]
    public function migrations(): string
    {
        return __DIR__.'/migrations';
    }

    #[\Override]
    public function routes(): void {}
}
