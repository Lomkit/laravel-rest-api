<?php

use Illuminate\Support\Facades\Route;
use Lomkit\Rest\Tests\Support\Http\Controllers\ModelController;

Route::group(['as' => 'api.', 'prefix' => 'api'], function () {
    \Lomkit\Rest\Facades\Rest::resource('models', ModelController::class);
});
