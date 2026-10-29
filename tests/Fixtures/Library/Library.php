<?php

namespace Lomkit\Rest\Tests\Fixtures\Library;

use Illuminate\Support\Facades\Route;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Fixtures\Fixture;

class Library implements Fixture
{
    public function migrations(): string
    {
        return __DIR__.'/migrations';
    }

    public function routes(): void
    {
        Route::prefix('api')->group(function (): void {
            Rest::resource('books', BookController::class);
            Rest::resource('loans', LoanController::class);
            Rest::resource('labels', LabelController::class);
        });
    }
}
