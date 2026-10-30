<?php

namespace Lomkit\Rest\Tests\Fixtures\Helpdesk;

use Illuminate\Support\Facades\Route;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Fixtures\Fixture;

class Helpdesk implements Fixture
{
    public function migrations(): string
    {
        return __DIR__.'/migrations';
    }

    public function routes(): void
    {
        Route::prefix('api')->group(function (): void {
            Rest::resource('tickets', TicketController::class);
            Rest::resource('public-tickets', PublicTicketController::class);
            Rest::resource('uncached-tickets', UncachedTicketController::class);
            Rest::resource('archived-tickets', ArchivedTicketController::class);
            Rest::resource('channels', ChannelController::class);
            Rest::resource('teams', TeamController::class);
            Rest::resource('tags', TagController::class);
        });
    }
}
