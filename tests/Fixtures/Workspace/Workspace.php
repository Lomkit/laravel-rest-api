<?php

namespace Lomkit\Rest\Tests\Fixtures\Workspace;

use Illuminate\Support\Facades\Route;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Fixtures\Fixture;

class Workspace implements Fixture
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
            Rest::resource('team-notes', TeamNoteController::class);
            Rest::resource('sql-only-team-notes', SqlOnlyTeamNoteController::class);
            Rest::resource('wide-index-team-notes', WideIndexTeamNoteController::class);
        });
    }
}
