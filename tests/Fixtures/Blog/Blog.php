<?php

namespace Lomkit\Rest\Tests\Fixtures\Blog;

use Lomkit\Rest\Tests\Fixtures\Fixture;

class Blog implements Fixture
{
    #[\Override]
    public function migrations(): string
    {
        return __DIR__.'/migrations';
    }

    #[\Override]
    public function routes(): void {}
}
