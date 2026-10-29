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

### Typed fields

`fields()` returns `Lomkit\Rest\Fields\Field` objects instead of column names. A string, or a name declared twice, throws `Lomkit\Rest\Exceptions\InvalidFieldException` with the name of the resource. Pick the type that matches the column, and add `->nullable()` to nullable columns:

| Column | Field |
| --- | --- |
| primary key | `ID::make('id')` |
| string, text | `Text::make('name')` |
| integer | `Integer::make('views')` |
| decimal, float | `Number::make('price')` |
| boolean | `Boolean::make('featured')` |
| date | `Date::make('published_on')` |
| datetime, timestamp | `DateTime::make('published_at')` |
| enum cast | `Enum::make('status')`, which reads the enum class from the model cast |
| json, array cast | `Json::make('metadata')` |

- `getFields()` returns a `Lomkit\Rest\Fields\FieldSet` instead of an array of names. Call `->names()` to get the old list.
- `rest:resource` generates `ID::make('id')`.
- `Lomkit\Rest\Fields\DateTime` has the same short name as PHP's `\DateTime`. In a file that uses both, alias one of them.
- `Resource` defines `__sleep()`, which leaves out the memoized fields, because fields can hold closures. A queued action's resource therefore rebuilds its fields in the worker, where the request has no user: `fields()` that depend on the user see a guest there. A resource that overrides `__sleep()` must declare `: array` and merge `parent::__sleep()`.

### Typed inputs

Scopes, instructions and actions declare their inputs with the same `Field` objects as resources. The payload does not change: scope parameters stay a positional list, and instruction and action fields stay a list of `{name, value}` entries.

```php
public function scopes(RestRequest $request): array
{
    return [
        Scope::make('publishedSince')->fields([Date::make('since')]),
        Scope::make('popular')->fields([Integer::make('min_views')->default(100)]),
    ];
}

public function fields(RestRequest $request): array // in an instruction or an action
{
    return [Boolean::make('featured')->default(true), Integer::make('min_views')->nullable()];
}
```

Scopes:

- `scopes()` returns `Lomkit\Rest\Scopes\Scope` objects instead of names. A string throws `Lomkit\Rest\Exceptions\InvalidScopeException` with the name of the resource.
- Each parameter binds to the field at the same position, in declared order. Reordering a scope's fields changes the positions clients must send.
- Parameters are validated against the field rules, cast to the field type and filled from `default()`. A position is required unless its field declares `default()`, and a nullable field accepts an explicit `null`. A missing or wrongly typed parameter now gives a 422 instead of a 500. Extra positions used to be ignored and named parameters used to bind by PHP argument name; both are now rejected with a 422, so clients that send them must switch to an exact positional list. The errors are under `search.scopes.<i>.parameters` or `search.scopes.<i>.parameters.<p>`.
- The model scope receives the cast values: a `Date` arrives as a `CarbonImmutable` at the start of the day, an `Integer` as an `int`. Update the scope's parameter types.
- Scopes run through Eloquent's `Builder::scopes()`, so the name must be a local scope of the model (a `scopeX()` method or a `#[Scope]` method). Builder methods and macros can no longer be called as scopes.
- `Lomkit\Rest\Query\Builder::scope()` returns the query builder instead of the scope's return value.
- `Resource::__sleep()` also leaves out the memoized scopes.

Instructions:

- `fields()` returns `Field` objects instead of a map of rules: `['min_views' => ['integer']]` becomes `[Integer::make('min_views')]`. The field rules are the type rules, `nullable` and `rules()`; rules that compare fields, such as `required_if:status,published`, keep working.
- `handle()` and `handleScout()` receive a name-to-value map, with each value cast to its field type and defaults filled in. A field that is absent and has no default is missing from the array, so read optional fields with `$fields['name'] ?? null`. An entry sent without `value` counts as absent.
- The fields of Scout instructions are validated now.
- The base `handleScout()` throws `Lomkit\Rest\Exceptions\InvalidInstructionException`, a `\LogicException`, instead of `http\Exception\RuntimeException`.
- `Builder::instruction()` and `ScoutBuilder::instruction()` throw `InvalidInstructionException` for a name the resource does not declare.
- An instruction applied to an include receives the relation's Eloquent builder.

Actions:

- `fields()` returns `Field` objects instead of a map of rules.
- `handle()`, `withBatch()` and queued jobs all receive the same cast name-to-value map, with defaults filled in. Before, queued and batched actions received the raw list of `{name, value}` entries.
- `OperateRequest::resolveFields()` returns that map.

Instructions and actions:

- `Lomkit\Rest\Concerns\Fieldable` and its `field()` helper are removed. Use `getFields($request)->find($name)`, which returns the `Field` or `null`. `getFields()` returns a `FieldSet` built on each call.
- The fields are not bound to a model, so an `Enum` field names its enum class: `Enum::make('status', PostStatus::class)`.
- For actions and Scout instructions, an entry without a name, or that is not an object, now gives a 422 under `fields.<i>.name` instead of a 500, as it already did for instructions. A name that is not a string fails with `must be a string`.
- `rest:action` and `rest:instruction` generate `ID::make('id')->rules('required')`.
- `Action`, `Instruction` and `BatchableAction` declare native types, and PHP requires subclasses to match them:
  - a redeclared property keeps its type: `public ?string $name`, `public bool $standalone`, `public bool $targeted`, `public int $chunkCount`;
  - an override declares its return type: `handle(): void`, `handleScout(): void`, `withBatch(): void`, `name(): string`, `uriKey(): string`, `standalone(): static`, `targeted(): static`, `isStandalone(): bool`, `isTargeted(): bool`, `handleRequest(): int`.
- An action's `handle()` returns `void`. Before, a synchronous action that returned `false` stopped the remaining chunks; the chunks now always run.

Meta:

- `Lomkit\Rest\Concerns\Metable` is removed. Actions, instructions and scopes use `Lomkit\Rest\Concerns\HasMeta`: `withMeta([...])` becomes `meta([...])`, which merges, and the `meta()` getter becomes `getMeta()`. The `$meta` property is private.

Details:

- `scopes` is a list of `{name, fields, meta}` instead of a list of names.
- The `fields` of actions, instructions and scopes are input entries `{name, type, nullable, default, rules, meta}`. `Enum` entries add `values`.
- Rules are described as strings: pipe strings are split, `Stringable` rules are cast to string, other rule objects are named by their snake class name (`enum`, `password`), and closures are left out.
- The resource `fields` stay a list of names.
