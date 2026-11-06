<?php

use Illuminate\Support\Facades\Route;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Support\Http\Controllers\AutomaticGatingController;
use Lomkit\Rest\Tests\Support\Http\Controllers\ModelController;
use Lomkit\Rest\Tests\Support\Http\Controllers\ModelHooksController;
use Lomkit\Rest\Tests\Support\Http\Controllers\ModelWithController;
use Lomkit\Rest\Tests\Support\Http\Controllers\OrPerimeterModelController;
use Lomkit\Rest\Tests\Support\Http\Controllers\OrPerimeterSoftDeletedModelController;
use Lomkit\Rest\Tests\Support\Http\Controllers\SoftDeletedModelController;

Route::group(['as' => 'api.', 'prefix' => 'api'], function () {
    Rest::resource('models', ModelController::class);
    Rest::resource('or-perimeter-models', OrPerimeterModelController::class);
    Rest::resource('model-hooks', ModelHooksController::class)->withSoftDeletes();
    Rest::resource('model-withs', ModelWithController::class);

    Rest::resource('automatic-gating', AutomaticGatingController::class);
    Rest::resource('soft-deleted-models', SoftDeletedModelController::class)->withSoftDeletes();
    Rest::resource('or-perimeter-soft-deleted-models', OrPerimeterSoftDeletedModelController::class)->withSoftDeletes();
});
