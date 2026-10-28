# Upgrade Guide

## Upgrading to 3.0 from 2.x

Version 3.0 is in development on the `3.x` branch. This guide lists each breaking change that is merged into that branch.

### Requirements

- PHP 8.3 or later.
- Laravel 13 or later. Applications on Laravel 12 stay on 2.x.
- Laravel Scout 11 or later, if you use full-text search.

### Built-in documentation generator removed

The package no longer generates its own OpenAPI document. The Scramble extension is now the only way to document the API.

These items are removed:

- the `/api-documentation` route and its Swagger UI page
- the `rest:documentation` and `rest:documentation-provider` commands, and the `rest.rest-documentation-service-provider.stub` stub
- the `documentation` key of `config/rest.php`
- `Rest::withDocumentationCallback()`
- the `Lomkit\Rest\Documentation` namespace
- the controller methods `generateDocumentation*Operation()` and their trait, `ExtendsDocumentationOperations`

To upgrade:

1. Remove the `documentation` key from your published `config/rest.php`.
2. Remove the calls to `Rest::withDocumentationCallback()`, and the service provider that `rest:documentation-provider` created.
3. Remove the `generateDocumentation*Operation()` methods from your controllers.
4. Install `dedoc/scramble` 0.13. The package registers its extension, so `config/scramble.php` needs no change. If you added `Lomkit\Rest\Scramble\LomkitLaravelRestApiOperationExtension` to its `extensions`, you can remove it.

### Request context

Each package request now owns a `RestContext`. The context holds the request and one instance of each resource for that request. Resources read the request from their context, not from the container.

These items change:

- `Controller::newResource()` is removed. In a controller action, use `$request->context()->resource(static::$resource)`.
- `app(RestRequest::class)` no longer returns the request in progress. It builds a new request. In a resource, read the request from the method parameter or from `$this->context()->request`.
- The query builder methods that only exist on Eloquent (`mutate()`, `mutations()`, `applyMutation()`, `disableSecurity()`, `disableDefaultLimit()`) are declared on a new contract, `Lomkit\Rest\Contracts\EloquentQueryBuilder`. If you bind your own `QueryBuilder` implementation, it must implement `EloquentQueryBuilder`. The package resolves `EloquentQueryBuilder` through your `QueryBuilder` binding.

### Routing

- `operate()` takes only the request: `operate(OperateRequest $request)`. It reads the action key from the route by name, with `$request->actionKey()`. A controller that overrides `operate($request, $action)` no longer loads: remove the second parameter. Actions now also work under route groups with URI or domain parameters, such as `Route::prefix('{tenant}')`.
- `Rest::resource()` now registers its routes through the package's own registrar. The default URIs, HTTP methods and route names do not change.
- `Rest::resource()` takes two arguments. A third argument (the old `$options` array) throws `Lomkit\Rest\Exceptions\InvalidResourceRouteException`, so a forgotten `['except' => ['destroy']]` cannot silently expose an endpoint. Use the fluent methods instead: `only()`, `except()`, `names()`, `middleware()` and `withSoftDeletes()`. For the `as` option, use `names('prefix.users')` or a route group with `name()`.
- The resource name must be one URI segment made of letters, digits, `-` and `_`. A dotted name (`users.posts`) throws: its `{user}` parameter was never used, so register the child resource on its own and scope its query in the resource. A slashed name (`admin/users`) throws: wrap the call in `Route::prefix('admin')`.
- `PendingResourceRegistration` is final and no longer extends Laravel's class. It keeps `only()`, `except()`, `withSoftDeletes()`, `names()`, `name()`, `middleware()`, `middlewareFor()`, `withoutMiddleware()`, `withoutMiddlewareFor()` and `register()`. `parameters()`, `parameter()`, `shallow()`, `scoped()`, `missing()` and `withTrashed()` are removed because they have no effect on these routes. `where()` and its variants, `metadata()` and the macros of Laravel's class are removed too: put `where()` or `metadata()` on the enclosing route group, for example `Route::where(['action' => '[a-z-]+'])->group(...)`. Repeated `names()` and `name()` calls add up: a prefix and exact names combine in any order.
- Every method that takes endpoint keys throws on an unknown key. The keys are `details`, `search`, `mutate`, `operate`, `destroy`, `restore` and `forceDestroy`, or the cases of `Lomkit\Rest\Http\Routing\Endpoint`. `withSoftDeletes()` only accepts `restore` and `forceDestroy`, and repeated calls add up.
- `withSoftDeletes([...])` no longer overrides `except()`. For example, `except('destroy')->withSoftDeletes('restore')` no longer registers `destroy`.
- `middleware()` adds to the package middleware instead of replacing it, so `EnforceExpectsJson` stays on every route, next to `HandlePrecognitiveRequests` when precognition is enabled. Requests without `Accept: application/json` now get JSON errors instead of a redirect. Repeated middleware calls add up, and resource middleware always comes before endpoint middleware.
- `Route::resourceVerbs()` now changes the package URIs through the keys `search`, `mutate`, `actions`, `restore` and `forceDestroy`. An application that already sets one of these keys will see its URIs change.
- Two endpoints of one resource cannot share a URI or a route name: the registration throws.
- `Lomkit\Rest\Http\Routing\ResourceRegistrar` is final, no longer extends Laravel's class and has a new API. `Rest` receives it from the container, so remove any container binding of `ResourceRegistrar` to your own class. The `Lomkit\Rest\Contracts\Http\Routing\Registrar` interface is removed.
- The force delete endpoint is renamed `forceDestroy`, like `destroy`, `ForceDestroyRequest` and the `forceDestroying` hooks: the controller method is `forceDestroy()`, the route name is `{name}.forceDestroy`, and the endpoint key and the `Route::resourceVerbs()` key are `forceDestroy`. The URI stays `{name}/force`. On resources, `forceDeleteQuery()` is renamed `forceDestroyQuery()`. The policy ability stays `forceDelete`, like Laravel's, and so does `performForceDelete()`, which calls Eloquent's `forceDelete()`.
