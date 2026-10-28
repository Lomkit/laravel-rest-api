<?php

namespace Lomkit\Rest\Tests\Fixtures\Catalog;

use Illuminate\Support\Facades\Route;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Fixtures\Fixture;

class Catalog implements Fixture
{
    public function migrations(): string
    {
        return __DIR__.'/migrations';
    }

    public function routes(): void
    {
        Route::prefix('api')->group(function (): void {
            Rest::resource('categories', CategoryController::class);
            Rest::resource('products', ProductController::class);
            Rest::resource('staff-products', StaffProductController::class);
        });
    }
}
