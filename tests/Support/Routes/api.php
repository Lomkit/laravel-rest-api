<?php

use Illuminate\Support\Facades\Route;

Route::group(['as' => 'api.', 'prefix' => 'api'], function () {
    \Lomkit\Rest\Facades\Rest::resource('models', \Lomkit\Rest\Tests\Support\Http\Controllers\ModelController::class);
    \Lomkit\Rest\Facades\Rest::resource('model-hooks', \Lomkit\Rest\Tests\Support\Http\Controllers\ModelHooksController::class)->withSoftDeletes();
    \Lomkit\Rest\Facades\Rest::resource('model-withs', \Lomkit\Rest\Tests\Support\Http\Controllers\ModelWithController::class);
    \Lomkit\Rest\Facades\Rest::resource('no-relationship-authorization-models', \Lomkit\Rest\Tests\Support\Http\Controllers\NoRelationshipAuthorizationModelController::class);

    \Lomkit\Rest\Facades\Rest::resource('no-exposed-fields', \Lomkit\Rest\Tests\Support\Http\Controllers\NoExposedFieldsController::class);
    \Lomkit\Rest\Facades\Rest::resource('automatic-gating', \Lomkit\Rest\Tests\Support\Http\Controllers\AutomaticGatingController::class);
    \Lomkit\Rest\Facades\Rest::resource('constrained', \Lomkit\Rest\Tests\Support\Http\Controllers\ConstrainedController::class);
    \Lomkit\Rest\Facades\Rest::resource('no-authorization', \Lomkit\Rest\Tests\Support\Http\Controllers\NoAuthorizationController::class);
    \Lomkit\Rest\Facades\Rest::resource('soft-deleted-models', \Lomkit\Rest\Tests\Support\Http\Controllers\SoftDeletedModelController::class)->withSoftDeletes();
});
