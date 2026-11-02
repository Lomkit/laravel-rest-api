<?php

namespace Lomkit\Rest\Tests\Feature\Serialization\Stubs\Charts;

use Illuminate\Support\Facades\Route;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Fixtures\Fixture;

class Charts implements Fixture
{
    #[\Override]
    public function migrations(): string
    {
        return __DIR__.'/migrations';
    }

    #[\Override]
    public function routes(): void
    {
        Route::prefix('api')->group(fn () => Rest::resource('albums', AlbumController::class));
    }
}
