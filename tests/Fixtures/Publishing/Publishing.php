<?php

namespace Lomkit\Rest\Tests\Fixtures\Publishing;

use Illuminate\Support\Facades\Route;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Fixtures\Fixture;

class Publishing implements Fixture
{
    public function migrations(): string
    {
        return __DIR__.'/migrations';
    }

    public function routes(): void
    {
        Route::prefix('api')->group(function (): void {
            Rest::resource('authors', AuthorController::class)->withSoftDeletes();
            Rest::resource('posts', PostController::class);
        });
    }
}
