<?php

namespace Lomkit\Rest\Tests\Feature\Relations\Persisters\Stubs\Memberships;

use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Fixtures\Fixture;

class Memberships implements Fixture
{
    #[\Override]
    public function migrations(): string
    {
        return __DIR__.'/migrations';
    }

    #[\Override]
    public function routes(): void
    {
        Rest::resource('projects', ProjectController::class);
    }
}
