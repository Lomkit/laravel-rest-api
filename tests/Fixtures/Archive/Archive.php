<?php

namespace Lomkit\Rest\Tests\Fixtures\Archive;

use Illuminate\Support\Facades\Route;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Fixtures\Fixture;

class Archive implements Fixture
{
    public function migrations(): string
    {
        return __DIR__.'/migrations';
    }

    public function routes(): void
    {
        Route::prefix('api')->group(function (): void {
            Rest::resource('documents', DocumentController::class)->withSoftDeletes();
        });
    }
}
