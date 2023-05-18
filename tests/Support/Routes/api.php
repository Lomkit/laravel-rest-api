<?php

use Illuminate\Support\Facades\Route;
use Lomkit\Rest\Tests\Support\Http\Controllers\ModelController;

Route::group(['as' => 'api.', 'prefix' => 'api'], function () {
    \Lomkit\Rest\Facades\Rest::resource('models', ModelController::class);

    \Lomkit\Rest\Facades\Rest::resource('no-exposed-fields', \Lomkit\Rest\Tests\Support\Http\Controllers\NoExposedFieldsController::class);
    \Lomkit\Rest\Facades\Rest::resource('soft-deleted-models', \Lomkit\Rest\Tests\Support\Http\Controllers\SoftDeletedModelController::class)->withSoftDeletes();
});
