<?php

use Illuminate\Support\Facades\Route;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Support\Http\Controllers\AutomaticGatingController;
use Lomkit\Rest\Tests\Support\Http\Controllers\ConstrainedController;
use Lomkit\Rest\Tests\Support\Http\Controllers\GatedModelController;
use Lomkit\Rest\Tests\Support\Http\Controllers\ModelController;
use Lomkit\Rest\Tests\Support\Http\Controllers\ModelHooksController;
use Lomkit\Rest\Tests\Support\Http\Controllers\ModelWithController;
use Lomkit\Rest\Tests\Support\Http\Controllers\ModelWithDefaultLimitController;
use Lomkit\Rest\Tests\Support\Http\Controllers\NoAuthorizationController;
use Lomkit\Rest\Tests\Support\Http\Controllers\NoExposedFieldsController;
use Lomkit\Rest\Tests\Support\Http\Controllers\NoRelationshipAuthorizationModelController;
use Lomkit\Rest\Tests\Support\Http\Controllers\OrPerimeterModelController;
use Lomkit\Rest\Tests\Support\Http\Controllers\OrPerimeterSoftDeletedModelController;
use Lomkit\Rest\Tests\Support\Http\Controllers\SearchableModelController;
use Lomkit\Rest\Tests\Support\Http\Controllers\SoftDeletedModelController;

Route::group(['as' => 'api.', 'prefix' => 'api'], function () {
    Rest::resource('models', ModelController::class);
    Rest::resource('or-perimeter-models', OrPerimeterModelController::class);
    Rest::resource('searchable-models', SearchableModelController::class);
    Rest::resource('model-hooks', ModelHooksController::class)->withSoftDeletes();
    Rest::resource('model-withs', ModelWithController::class);
    Rest::resource('model-with-default-limit', ModelWithDefaultLimitController::class);
    Rest::resource('no-relationship-authorization-models', NoRelationshipAuthorizationModelController::class);

    Rest::resource('gated-models', GatedModelController::class);

    Rest::resource('no-exposed-fields', NoExposedFieldsController::class);
    Rest::resource('automatic-gating', AutomaticGatingController::class);
    Rest::resource('constrained', ConstrainedController::class);
    Rest::resource('no-authorization', NoAuthorizationController::class);
    Rest::resource('soft-deleted-models', SoftDeletedModelController::class)->withSoftDeletes();
    Rest::resource('or-perimeter-soft-deleted-models', OrPerimeterSoftDeletedModelController::class)->withSoftDeletes();
});
