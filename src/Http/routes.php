<?php

use Illuminate\Support\Facades\Route;

if (config('rest.documentation.routing.enabled')) {
    Route::get('/', [\Lomkit\Rest\Http\Controllers\DocumentationController::class, 'index']);
}
