<?php

namespace Lomkit\Rest\Tests\Feature\Search\Stubs\Pinboard;

use Illuminate\Support\Facades\Route;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Fixtures\Fixture;

class Pinboard implements Fixture
{
    #[\Override]
    public function migrations(): string
    {
        return __DIR__.'/migrations';
    }

    #[\Override]
    public function routes(): void
    {
        Route::prefix('api')->group(fn () => Rest::resource('pins', PinController::class));
    }
}
