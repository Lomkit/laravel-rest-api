<?php

namespace Lomkit\Rest\Tests\Fixtures\Festival;

use Illuminate\Support\Facades\Route;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Fixtures\Fixture;

class Festival implements Fixture
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
            Rest::resource('stages', StageController::class);
            Rest::resource('shows', ShowController::class)
                ->middleware('auth')
                ->withSoftDeletes()
                ->withBatchProgress();
            Rest::resource('notes', NoteController::class);
        });
    }
}
