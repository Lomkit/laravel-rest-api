<?php

namespace Lomkit\Rest\Tests\Fixtures\Lab;

use Illuminate\Support\Facades\Route;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Fixtures\Fixture;

class Lab implements Fixture
{
    #[\Override]
    public function migrations(): string
    {
        return __DIR__.'/migrations';
    }

    #[\Override]
    public function routes(): void
    {
        Route::prefix('api')->group(function (): void {
            Rest::resource('instruments', InstrumentController::class);
            Rest::resource('recorders', RecorderController::class);
            Rest::resource('sensors', SensorController::class);
        });
    }
}
