<?php

namespace Lomkit\Rest\Tests\Fixtures\Accounts;

use Lomkit\Rest\Tests\Fixtures\Fixture;

class Accounts implements Fixture
{
    public function migrations(): string
    {
        return __DIR__.'/migrations';
    }

    public function routes(): void {}
}
