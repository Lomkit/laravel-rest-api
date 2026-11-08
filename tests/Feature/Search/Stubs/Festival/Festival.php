<?php

namespace Lomkit\Rest\Tests\Feature\Search\Stubs\Festival;

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
            Rest::resource('notes', NoteController::class);
            Rest::resource('stages', StageController::class);
        });
    }
}
