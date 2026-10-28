<?php

use Illuminate\Support\Facades\Route;
use Lomkit\Rest\Http\Controllers\DocumentationController;

if (config('rest.documentation.routing.enabled')) {
    Route::get('/', [DocumentationController::class, 'index']);
}
