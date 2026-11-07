<?php

use Illuminate\Support\Facades\Route;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Support\Http\Controllers\AutomaticGatingController;
use Lomkit\Rest\Tests\Support\Http\Controllers\ModelController;
use Lomkit\Rest\Tests\Support\Http\Controllers\SoftDeletedModelController;

Route::group(['as' => 'api.', 'prefix' => 'api'], function () {
    Rest::resource('models', ModelController::class);

    Rest::resource('automatic-gating', AutomaticGatingController::class);
    Rest::resource('soft-deleted-models', SoftDeletedModelController::class)->withSoftDeletes();
});
