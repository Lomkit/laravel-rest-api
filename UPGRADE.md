# Upgrade Guide

## Upgrading to 3.0 from 2.x

Version 3.0 is in development on the `3.x` branch. This guide lists each breaking change that is merged into that branch.

### Requirements

- PHP 8.3 or later.
- Laravel 13.30 or later. Applications on Laravel 12 stay on 2.x, and applications on an earlier Laravel 13 release update Laravel first: `composer update laravel/framework`.
- Laravel Scout 11.9 or later, when Scout is installed: Composer refuses earlier releases next to the package. Scout is only needed for the `ScoutEngine` text search, see [Search engines](#search-engines).

### Configuration

This section lists each change to `config/rest.php`. Compare your published file with `vendor/lomkit/laravel-rest-api/config/rest.php`, then apply the changes below.

`authorizations.enabled` and `gates.enabled` are removed. To turn the policies or the gates off for the whole application, override `isAuthorizingEnabled()` or `isGatingEnabled()` in your base resource, `app/Rest/Resources/Resource.php`, which your resources extend, and return `false`:

```php
namespace App\Rest\Resources;

use Lomkit\Rest\Http\Resource as RestResource;

abstract class Resource extends RestResource
{
    public function isAuthorizingEnabled(): bool
    {
        return false;
    }

    public function isGatingEnabled(): bool
    {
        return false;
    }
}
```

`Resource::isAuthorizingEnabled()` and `Resource::isGatingEnabled()` return `true` and no longer read the config. Override them the same way in one resource to turn its policies or its gates off. The `Lomkit\Rest\Concerns\Resource\DisableAuthorizations` and `DisableGates` traits are removed: a resource that uses one overrides the method instead.

The keys that change:

| Key | Change |
| --- | --- |
| `authorizations.enabled` | Removed. Override `isAuthorizingEnabled()` and return `false`. |
| `gates.enabled` | Removed. Override `isGatingEnabled()` and return `false`. |
| `authorizations.strict` | Added. `false` by default, or the value of `REST_AUTHORIZATIONS_STRICT`. |
| `authorizations.cache.enabled` | `true` by default, or the value of `REST_AUTHORIZATIONS_CACHE_ENABLED`. Only the decisions that a resource declares in `authorizationCache()` and the results of the metrics that declare `cacheFor()` are kept, see "Cache" in [Authorization](#authorization). |
| `authorizations.cache.default` | Removed. Each resource gives the seconds of each ability in `authorizationCache()`. |
| `authorizations.cache.store` | Added. The cache store of the decisions and of the metric results, the default store when `null`, or the value of `REST_AUTHORIZATIONS_CACHE_STORE`. |
| `limits` | Added, in four groups: `sizes`, `depths`, `totals` and `lengths`. |
| `search.max_nesting_depth` | Moved to `limits.depths.filters`. |
| `documentation` | Removed with the documentation generator. |
| `morph.type_key` | Added. `@type` by default, see [Responses](#responses). |
| `search.select_columns` | Added. `true` by default, see [Selects, sorts and aggregates](#selects-sorts-and-aggregates). |
| `transactions.attempts` | Added. `1` by default, or the value of `REST_TRANSACTION_ATTEMPTS`: how many times a write runs on a deadlock, see [Writes](#writes). |
| `search.more_key` | Added. `@more` by default, see [Search](#search). |
| `gates.message.enabled` | Removed. Each answer of `gates` holds `allowed` and `message`, see [Responses](#responses). |

`gates.key`, `gates.names` and `precognition.enabled` keep their name and their default. `gates.names` also renames the gates that a resource declares, by their default name, such as `'authorized_to_download' => 'can_download'`, see [Responses](#responses). The blocks are now in this order: `authorizations`, `gates`, `morph`, `search`, `transactions`, `limits`, `precognition`.

Before, in the file that 2.x published, without its comments and its `documentation` block:

```php
'gates' => [
    'enabled' => true,
    'key' => 'gates',
    'message' => [
        'enabled' => false,
    ],
    'names' => [
        // the six names below
    ],
],

'authorizations' => [
    'enabled' => true,
    'cache' => [
        'enabled' => true,
        'default' => 5,
    ],
],

'search' => [
    'max_nesting_depth' => 1,
],

'precognition' => [
    'enabled' => false,
],
```

After, in 3.0:

```php
'authorizations' => [
    'strict' => env('REST_AUTHORIZATIONS_STRICT', false),

    'cache' => [
        'enabled' => env('REST_AUTHORIZATIONS_CACHE_ENABLED', true),
        'store' => env('REST_AUTHORIZATIONS_CACHE_STORE'),
    ],
],

'gates' => [
    'key' => 'gates',

    'names' => [
        'authorized_to_view' => 'authorized_to_view',
        'authorized_to_create' => 'authorized_to_create',
        'authorized_to_update' => 'authorized_to_update',
        'authorized_to_delete' => 'authorized_to_delete',
        'authorized_to_restore' => 'authorized_to_restore',
        'authorized_to_force_delete' => 'authorized_to_force_delete',
    ],
],

'morph' => [
    'type_key' => '@type',
],

'search' => [
    'select_columns' => true,

    'more_key' => '@more',
],

'transactions' => [
    'attempts' => env('REST_TRANSACTION_ATTEMPTS', 1),
],

'limits' => [
    'sizes' => [
        'filters' => 100,
        'sorts' => 20,
        'selects' => 100,
        'scopes' => 10,
        'aggregates' => 20,
        'includes' => 20,
        'instructions' => 10,
        'fields' => 100,
        'values' => 100,
        'mutate' => 100,
        'relation_entries' => 100,
        'resources' => 1000,
    ],

    'depths' => [
        'filters' => 1,
        'includes' => 3,
        'mutations' => 3,
    ],

    'totals' => [
        'filters' => 500,
        'entries' => 1000,
    ],

    'lengths' => [
        'patterns' => 255,
    ],
],

'precognition' => [
    'enabled' => false,
],
```

Authorizations:

- `authorizations.strict` turns strict mode on, see "Checks the request did not plan" in [Authorization](#authorization). Turn it on in your test suite, for example with `<env name="REST_AUTHORIZATIONS_STRICT" value="true"/>` in `phpunit.xml`.
- The cache of decisions between requests is on, and keeps only the decisions that a resource declares in `authorizationCache()`: an application that declares none keeps no decision between requests. `REST_AUTHORIZATIONS_CACHE_ENABLED=false` turns it off. `authorizations.cache.store` names the cache store, the default store when `null`; `REST_AUTHORIZATIONS_CACHE_STORE` sets it. See "Cache" in [Authorization](#authorization).

Limits:

- The `limits` block sets the most entries of each list of a request, how deep filters, includes and relations nest, two totals, and the most characters of a pattern. See "Limits" in [Validation engine](#validation-engine) for what each key covers.
- `search.max_nesting_depth` moved to `limits.depths.filters`, with the same default of 1 and the same meaning. Move its value to the new key, and remove `max_nesting_depth` from the `search` block, which now holds `select_columns`.
- `limits.sizes` has no `gates` key: a `gates` list takes each ability once, see [Responses](#responses). Remove `limits.sizes.gates` from your file if it lists it.

Published files:

- The package defaults merge into your published `config/rest.php` at every level. A nested key that your file leaves out, such as `gates.names.authorized_to_restore` or `limits.sizes.filters`, gets its default value. Before, a top-level key such as `gates` replaced all the defaults under it.
- Remove from your file the keys that 3.0 removed or moved: `authorizations.enabled`, `authorizations.cache.default`, `gates.enabled`, `gates.message`, `search.max_nesting_depth` and the `documentation` block, after moving the value of `search.max_nesting_depth` to `limits.depths.filters`.
- A key under `limits` that the package does not know throws an exception that names it, so that a typo does not keep the default without notice: "The [rest.limits.sizes.filter] option does not exist." An unknown key elsewhere is ignored.

Types:

- Each request reads the values once, and checks their type. A value of the wrong type throws an exception that names the key. Before, the values were cast without a check: the string `"false"` turned strict mode, the cache and Precognition on.
  - `authorizations.strict`, `authorizations.cache.enabled`, `search.select_columns` and `precognition.enabled` take `true`, `false`, or the strings `"1"`, `"0"`, `"true"` and `"false"`. Anything else throws, `null` included: "The [rest.authorizations.strict] option must be a boolean."
  - Each value of `limits` takes an integer of 0 or more, or a string of digits, as `env()` gives it: "The [rest.limits.sizes.filters] option must be an integer of 0 or more."
  - `transactions.attempts` takes an integer of 1 or more, or a string of digits: "The [rest.transactions.attempts] option must be an integer of 1 or more."
  - `gates.key`, `morph.type_key` and `search.more_key` take a string that is not empty: "The [rest.gates.key] option must be a non-empty string."
  - `authorizations.cache.store` takes `null` or a string that is not empty: "The [rest.authorizations.cache.store] option must be null or a non-empty string."
  - `gates.names` takes a map of strings that holds the six names above: "The [rest.gates.names] option must be a map of strings." for another value, and "The [rest.gates.names.authorized_to_view] option must be a string." for a missing name or one that is not a string.
- `precognition.enabled` is read when the routes are registered, `limits` when a request first reads the limits, and the other values together, the first time a request needs one of them.
- The exception is `Lomkit\Rest\Exceptions\InvalidConfigException`, which extends `InvalidArgumentException`.

### Built-in documentation generator removed

The package no longer generates its own OpenAPI document. The Scramble extension is now the only way to document the API.

These items are removed:

- the `/api-documentation` route and its Swagger UI page
- the `rest:documentation` and `rest:documentation-provider` commands, and the `rest.rest-documentation-service-provider.stub` stub
- the `documentation` key of `config/rest.php`, see [Configuration](#configuration)
- `Rest::withDocumentationCallback()`
- the `Lomkit\Rest\Documentation` namespace
- the controller methods `generateDocumentation*Operation()` and their trait, `ExtendsDocumentationOperations`

To upgrade:

1. Remove the calls to `Rest::withDocumentationCallback()`, and the service provider that `rest:documentation-provider` created.
2. Remove the `generateDocumentation*Operation()` methods from your controllers.
3. Install `dedoc/scramble` 0.13.48 or a later 0.13 release. The package registers its documentation itself, so `config/scramble.php` needs no change. If its `extensions` list `Lomkit\Rest\Scramble\LomkitLaravelRestApiOperationExtension`, remove the line: the class is removed. See [OpenAPI documentation](#openapi-documentation).

### Request context

Each package request now owns a `RestContext`. The context holds the request and one instance of each resource for that request. Resources read the request from their context, not from the container.

These items change:

- `Controller::newResource()` is removed. In a controller action, use `$request->context()->resource(static::resourceClass())`.
- `app(RestRequest::class)` no longer returns the request in progress. It builds a new request. In a resource, read it with `$this->request()`.
- A package request reads its resource from the class of the route controller, which must extend `Lomkit\Rest\Http\Controllers\Controller`. On another route, such as a closure route, the request throws `Lomkit\Rest\Exceptions\InvalidControllerException`. Before, it failed with a PHP error.

### Resource definitions

A resource holds no request state. Its context evaluates `fields()`, `scopes()`, `relations()`, `instructions()`, `actions()`, `metrics()`, `limits()`, `text()` and `gates()` once per request, when they are first read, and keeps the results until the request ends. The next request evaluates them again, with its own user.

- The declarations take no argument: `fields()`, `relations()`, `scopes()`, `limits()`, `alwaysSelect()`, `actions()`, `instructions()`, `text()`, `defaultOrderBy()`, `rules()`, `createRules()`, `updateRules()` and `gates()` of a resource, and the `fields()` of actions and instructions. `abilities()` takes the endpoint only: `abilities(Endpoint $endpoint): array`. Remove the `RestRequest $request` parameter from each override, and the argument of its `parent::` call: PHP refuses to load a class whose override keeps it, and names the method. A declaration that reads the request or the user calls `$this->request()`, the request of the resource's context: `if ($this->request()->user()?->is_staff) { ... }`. An action or an instruction calls `$this->request()` too: it returns the request of its resource. A resource, action or instruction that already defines a method named `request()` must give it this signature: `request(): Lomkit\Rest\Http\Requests\RestRequest`.
- The `calculatedFields`, `calculatedScopes`, `calculatedRelations`, `calculatedInstructions`, `calculatedActions`, `calculatedLimits`, `calculatedScoutFields` and `calculatedScoutInstructions` properties of resources are removed. Read the declarations from `$resource->definition()`, see below. `getScoutFields()`, `getScoutInstructions()` and `scoutInstruction()` are removed with the declarations they read, and so is the `Lomkit\Rest\Concerns\Resource\Scoutable` trait, see [Search engines](#search-engines).
- A queued or batched action does not carry the resource or its declarations: the worker builds the context again for the user who sent the request, and evaluates the declarations there, see [Actions](#actions). Closures in the declarations, such as field rules, scope fields, relation constraints, or actions and instructions built with closures, never reach the queue, and neither do the action and its input values.
- `Resource` does not define `__sleep()`, and the `Lomkit\Rest\Concerns\Resource\ForgetsRequestState` trait is removed. A resource that overrides `__sleep()` must not call `parent::__sleep()`.
- `$resource->definition()` returns the `Lomkit\Rest\Http\ResourceDefinition` that the context keeps for the request. It reads `fields()` (a `FieldSet`), `relations()`, `scopes()`, `actions()`, `metrics()`, `instructions()`, `limits()`, `text()` and `gates()`, and finds one declaration with `relation($name)` (a dotted path is accepted), `scope($name)`, `instruction($key)`, `actionExists($key)`, `action($key)` and `metric($key)`. `inputFields($actionOrInstruction)` returns the fields of an action or an instruction of the resource, a `FieldSet` read once per request. `getFields()`, `getRelations()`, `getScopes()`, `getLimits()`, `getActions()`, `getInstructions()`, `relation()`, `scope()`, `actionExists()`, `action()` and `instruction()` are removed from `Resource`, and `getFields()` from actions and instructions: replace `$resource->getFields()` with `$resource->definition()->fields()`, `$resource->getLimits()` with `$resource->definition()->limits()->allowed` and `$action->getFields($request)` with `$resource->definition()->inputFields($action)`. An override of a removed method is no longer called: change the declaration instead. A resource that already defines a method named `definition()` must give it this signature: `definition(): Lomkit\Rest\Http\ResourceDefinition`.
- `$resource->definition()->limits()` returns a `Lomkit\Rest\Http\ResourceLimits`: `allowed` holds the values of `limits()`, and `defaultLimit`, `includeLimit` and `maxTotal` the properties of the resource, checked as described in [Search](#search).
- `Lomkit\Rest\Concerns\Resource\ConfiguresRestParameters` is removed. `scopes()` is declared by `Lomkit\Rest\Concerns\Resource\DeclaresScopes`, `limits()` by `Paginable` and `alwaysSelect()` by `DeclaresFields`. A class that used the trait uses these three.

### Routing

- `operate()` takes only the request: `operate(OperateRequest $request)`. `$request->actionKey()` returns the key of the action's route, or `null` on the route of unknown keys, see below. A controller that overrides `operate($request, $action)` no longer loads: remove the second parameter. Actions now also work under route groups with URI or domain parameters, such as `Route::prefix('{tenant}')`.
- `Rest::resource()` now registers its routes through the package's own registrar. The URIs, HTTP methods and route names of the endpoints do not change; each action gets its own route, see below.
- `Rest::resource()` takes two arguments. Replace the `$options` array with the fluent methods: `only()`, `except()`, `names()`, `middleware()` and `withSoftDeletes()`. For the `as` option, use `names('prefix.users')` or a route group with `name()`. PHP ignores a third argument, so search your route files for each call that still passes one: a forgotten `['except' => ['destroy']]` registers `destroy`.
- The resource name must be one URI segment made of letters, digits, `-` and `_`. A dotted name (`users.posts`) throws: its `{user}` parameter was never used, so register the child resource on its own and scope its query in the resource. A slashed name (`admin/users`) throws: wrap the call in `Route::prefix('admin')`.
- `PendingResourceRegistration` is final and no longer extends Laravel's class. It keeps `only()`, `except()`, `withSoftDeletes()`, `names()`, `name()`, `middleware()`, `middlewareFor()`, `withoutMiddleware()`, `withoutMiddlewareFor()` and `register()`. `parameters()`, `parameter()`, `shallow()`, `scoped()`, `missing()` and `withTrashed()` are removed because they have no effect on these routes. `where()` and its variants, `metadata()` and the macros of Laravel's class are removed too: put `where()` or `metadata()` on the enclosing route group, for example `Route::where(['action' => '[a-z-]+'])->group(...)`. Repeated `names()` and `name()` calls add up: a prefix and exact names combine in any order.
- Every method that takes endpoint keys throws on an unknown key. The keys are `details`, `search`, `mutate`, `operate`, `metric`, `destroy`, `restore`, `forceDestroy` and `batch`, or the cases of `Lomkit\Rest\Http\Routing\Endpoint`. `withSoftDeletes()` only accepts `restore` and `forceDestroy`, and repeated calls add up.
- `Endpoint` has a `Batch` case, the opt-in route of `withBatchProgress()`, see [Actions](#actions). A `match` on `Endpoint` without a default arm handles it. `Endpoint::optIns()` is replaced by `Endpoint::softDeletes()`, which returns `Restore` and `ForceDestroy`.
- `withSoftDeletes([...])` no longer overrides `except()`. For example, `except('destroy')->withSoftDeletes('restore')` no longer registers `destroy`.
- `withSoftDeletes()` throws `InvalidResourceException` when the routes are registered if the model of the resource does not use `Illuminate\Database\Eloquent\SoftDeletes`. Add the trait to the model, or remove `withSoftDeletes()`.
- `middleware()` adds to the package middleware instead of replacing it, so `EnforceExpectsJson` stays on every route, next to `HandlePrecognitiveRequests` when precognition is enabled. Requests without `Accept: application/json` now get JSON errors instead of a redirect. Repeated middleware calls add up, resource middleware always comes before endpoint middleware, and endpoint middleware before the middleware of an action.
- `Route::resourceVerbs()` now changes the package URIs through the keys `search`, `mutate`, `actions`, `metrics`, `restore` and `forceDestroy`. An application that already sets one of these keys will see its URIs change.
- Two endpoints of one resource cannot share a URI or a route name: the registration throws.
- Each action that the resource declares has its own route, `POST {name}/actions/{uriKey}`, named `{name}.operate.{uriKey}`, registered before `POST {name}/actions/{action}` (`{name}.operate`). That last route answers the keys of no action: `403` to a user that `viewAny` refuses, `404` to the others, as before. `php artisan route:list` shows one route per action:

  ```
  POST  api/tickets/actions/close-tickets   tickets.operate.close-tickets
  POST  api/tickets/actions/{action}        tickets.operate
  ```

  `names('helpdesk')` gives `helpdesk.operate.close-tickets`, `name('operate', 'tickets.run')` gives `tickets.run.close-tickets`, and `Route::resourceVerbs(['actions' => 'run'])` gives `tickets/run/close-tickets`. `route('tickets.operate', ['action' => 'close-tickets'])` still gives the URL of the action.
- `Rest::resource()` reads the `actions()` of the resource when it registers the routes: at boot, without a user, or during `php artisan route:cache`. Return the same actions for every user and every request: refuse an action to some users with its `abilities()`, not by leaving it out of `actions()`. An action that `actions()` returns only later has no route and gets the answer of an unknown key, and so does a route whose action `actions()` no longer returns. With cached routes, run `php artisan route:cache` after you add, rename or remove an action. Keep `actions()` free of queries. An action that throws when the actions are read, such as `Lomkit\Rest\Exceptions\InvalidActionException`, now throws when the routes register.
- Two actions of one resource with the same `uriKey()` throw `InvalidResourceRouteException` when the routes register: "Two endpoints of the resource use the route [POST tickets/actions/close-tickets].". Before, the second action could not be called.
- Each metric has its own route in the same way: `POST {name}/metrics/{uriKey}`, named `{name}.metric.{uriKey}`, before `POST {name}/metrics/{metric}` (`{name}.metric`). `Rest::resource()` reads `metrics()` with `actions()`, with the same rules: the same metrics for every user, `route:cache` again after a change, two metrics with one `uriKey()` throw, a metric that `metrics()` returns only later gets the answer of an unknown key. See [Metrics](#metrics).
- `Rest` and `PendingResourceRegistration` take the container as a last constructor argument. `Rest` comes from the container as before.
- `Lomkit\Rest\Http\Routing\ResourceRegistrar` is final, no longer extends Laravel's class and has a new API. `Rest` receives it from the container, so remove any container binding of `ResourceRegistrar` to your own class. The `Lomkit\Rest\Contracts\Http\Routing\Registrar` interface is removed.
- The force delete endpoint is renamed `forceDestroy`, like `destroy`, `ForceDestroyRequest` and the `forceDestroying` hooks: the controller method is `forceDestroy()`, the route name is `{name}.forceDestroy`, and the endpoint key and the `Route::resourceVerbs()` key are `forceDestroy`. The URI stays `{name}/force`. The policy ability stays `forceDelete`, like Laravel's, and so does `performForceDelete()`, which calls Eloquent's `forceDelete()`.

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
| json, jsonb, JSON cast | `Json::make('metadata')`, with or without a JSON cast on the model |

- `Json` works with or without a JSON cast on the model, or on the pivot class for a pivot field. When the model writes the attribute as it is, with no cast or a `string`, `encrypted` or `AsStringable` cast, and no set mutator, the field encodes the value before Eloquent writes it, and the response returns the decoded value. Any other cast, such as `array`, `json`, `collection`, `AsArrayObject`, `AsCollection`, `AsFluent`, `AsVector` or a cast class of the application, and a set mutator (`setMetadataAttribute()` or an `Attribute` with a `set` callback) receive the value as sent and encode it, as before. A `fillUsing()` callback still receives the value as sent. A `json` pivot column on the default `Pivot` class needs no custom pivot model.
- A `Json` value accepts at most 64 levels of nested arrays and objects, the top level included: a deeper value gets a 422 on its key in every write, action and instruction, where MySQL used to give a 500 above 100 levels. Send flatter values.
- `$resource->definition()->fields()` returns a `Lomkit\Rest\Fields\FieldSet` instead of an array of names. Call `->names()` to get the old list.
- A field named like the gates key, `rest.gates.key` (`gates` by default), throws `Lomkit\Rest\Exceptions\InvalidResourceException` when the fields of its resource are first read, because the gates of a row are returned under that key. Rename the field, or set `rest.gates.key` to another name.
- `rest:resource` generates `ID::make()` with the key name of the model, `ID::make('id')` by default. See [Generators](#generators).
- `Lomkit\Rest\Fields\DateTime` has the same short name as PHP's `\DateTime`. In a file that uses both, alias one of them.
- A field can declare `label()` and `messages()`. The label names the field's key in validation messages, and the messages replace Laravel's text for the rules of that key. See "Labels and messages" in [Validation engine](#validation-engine).
- A field gives its OpenAPI type with `toSchema()`, which returns a Scramble type (`Dedoc\Scramble\Support\Generator\Types\Type`): `ID` an `integer` or a `string` from the key type (`format: uuid` for a UUID key), `Text` a `string`, `Number` a `number`, `Integer` an `integer`, `Boolean` a `boolean`, `Date` a `string` with `format: date`, `DateTime` a `string` with `format: date-time`, `Enum` an `enum` of its backing values, `Json` an `object`. The document adds `null` for a `nullable()` field and the `meta()` of the field under `x-lomkit-meta`. A field type of the application documents a `string` until it overrides `toSchema()`. The package reads it only when Scramble builds the document:

  ```php
  use Dedoc\Scramble\Support\Generator\Types\NumberType;
  use Dedoc\Scramble\Support\Generator\Types\Type;

  class Money extends Field
  {
      public function toSchema(): Type
      {
          return new NumberType;
      }
  }
  ```

- `examples(...$values)` gives the example values of a field, which its OpenAPI type lists under `examples`: `Text::make('title')->examples('Weekly report')`, `Text::make('priority')->examples('low', 'normal', 'urgent')`. Each call replaces the list. A value is an integer, a float, a string, a boolean, an array or `null`, and is not checked when the field is declared. An `Enum` field lists its cases in `enum` and needs no example. The request examples of the document use the first value.

### Typed inputs

Scopes, instructions and actions declare their inputs with the same `Field` objects as resources. The payload does not change: scope parameters stay a positional list, and instruction and action fields stay a list of `{name, value}` entries. Input fields take `rules()` only: `creationRules()` and `updateRules()` describe writes, so declaring them on an input field throws `InvalidFieldException`.

```php
public function scopes(): array
{
    return [
        Scope::make('publishedSince')->fields([Date::make('since')]),
        Scope::make('popular')->fields([Integer::make('min_views')->default(100)]),
    ];
}

public function fields(): array // in an instruction or an action
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
- `Builder::scope()` and `applyScopes()` are removed. Each scope of the search runs through `Lomkit\Rest\Query\Clauses\ScopeClause::of()`, with the cast values of its `ScopeNode`.

Instructions:

- `fields()` returns `Field` objects instead of a map of rules: `['min_views' => ['integer']]` becomes `[Integer::make('min_views')]`. The field rules are the type rules, `nullable` and `rules()`; rules that compare fields, such as `required_if:status,published`, `date_equals:since` or `Rule::date()->after('since')`, compare with the other inputs of the instruction, as described under Validation engine.
- `handle()` and `handleEngine()` receive a `Lomkit\Rest\Instructions\InstructionFields`, which reads the inputs as `ActionFields` does, see [Actions](#actions). An entry sent without `value` counts as absent.
- `Builder::instruction()` and `applyInstructions()` are removed. Each instruction of the search runs through `InstructionClause::of()`, with the cast values of its `InstructionNode`.
- An instruction applied to an include receives the relation's Eloquent builder.
- The conditions that a scope or an instruction adds form one group, joined to the rest of the search with `and`: an `orWhere()` at their top level no longer widens `perimeterQuery()`, the filters, the other scopes or the soft-delete condition. Check the scopes and instructions that start with `orWhere()` or hold one at their top level.

Actions:

- `fields()` returns `Field` objects instead of a map of rules.
- `handle()` and `withBatch()` receive the same cast values, with defaults filled in, in a `Lomkit\Rest\Actions\ActionFields`, see [Actions](#actions); a queued action receives them in the worker too. Before, queued and batched actions received the raw list of `{name, value}` entries.
- `OperateRequest::resolveFields()` is removed, see [Actions](#actions).
- The `fields()` of an action run once per request, during validation. Before, they ran twice. The request keeps the fields and the action does not, so a queued job still carries no declarations.

Instructions and actions:

- `Lomkit\Rest\Concerns\Fieldable` and its `field()` helper are removed. Use `$resource->definition()->inputFields($action)->find($name)`, which returns the `Field` or `null`.
- The fields are not bound to a model, so an `Enum` field names its enum class: `Enum::make('status', PostStatus::class)`.
- For actions, an entry without a name, or that is not an object, now gives a 422 under `fields.<i>.name` instead of a 500, as it already did for instructions. A name that is not a string fails with `must be a string`.
- `rest:action` and `rest:instruction` generate a `fields()` method that returns `[]`. Before, it declared a required `id` field that the action or the instruction did not use. See [Generators](#generators).

Meta:

- `Lomkit\Rest\Concerns\Metable` is removed. Actions, instructions and scopes use `Lomkit\Rest\Concerns\HasMeta`: `withMeta([...])` becomes `meta([...])`, which merges, and the `meta()` getter becomes `getMeta()`. The `$meta` property is private.

Details: scopes, instructions and actions list their inputs as entries, see [Details](#details).

### Actions

A queued or batched action no longer serializes its resource, its rows and its input values. The job holds the resource class, the action class and key, the input values as the client sent them, the keys of the rows and the user who sent the request. The worker builds the request context again for that user, finds the action among the `actions()` of the resource, casts the input values with the action's `fields()` and loads the rows by key.

- In the worker, `$this->request()->user()` returns the user who sent the request: in `handle()`, in `perimeterQuery()`, in the declarations and in the policies that the package checks, such as `$this->resource->authorizedTo('update', $post)`. Before, the worker ran as a guest. The worker uses the guard of the request, and logs nobody in: `auth()->user()` and the `Gate` facade still see no user there, so read the user with `$this->request()->user()`. An action whose `abilities()` returns `[]`, sent by a guest, runs as a guest.
- The worker finds the user with the user provider of the guard. When the provider no longer finds the user, or returns a user of another class, the job fails with `Lomkit\Rest\Exceptions\QueuedActionException`: "The queued action cannot find the user [App\Models\User] with the identifier [7] through the [web] guard.". A user whose identifier is not an integer or a string cannot queue an action: the request fails with the same exception.
- The worker loads the rows again by key, in the order of the request, with the global scopes of the model, with `search.trashed` when the search sends it, and through `perimeterQuery()` with the user. A row that was deleted, soft-deleted or moved out of the perimeter before the worker runs is not given to the action, and a job whose rows are all gone does not call `handle()`. Before, the worker restored the rows by key without `perimeterQuery()` and without global scopes, soft-deleted rows included. For a text search on a search engine, the worker loads the keys without `perimeterQuery()`, as the request does, see [Search engines](#search-engines).
- The worker loads the columns of the rows, without the `includes` and `aggregates` of the search. Load the relations that `handle()` needs in `handle()`: `$models->load('author')`. Before, the worker loaded again the relations that the request had loaded.
- The worker finds the action by its `uriKey()` among the `actions()` of the resource, so the options set when declaring it are kept. When the resource no longer declares it, for example after a deploy, or when `actions()` depends on the user, the job fails with `QueuedActionException`: "The resource [App\Rest\Resources\PostResource] no longer declares the action [App\Rest\Actions\PublishPosts] under the key [publish-posts].". The `fields()` of the action run again in the worker.
- The worker finds the user, the resource and the rows on the database connections that are current when the job runs. With several tenants, initialise the tenant in the worker before the job runs, as the `QueueTenancyBootstrapper` of stancl/tenancy 3 does from the job payload. Without it, with a database per tenant, the job fails on the central connection when it looks up the user; with a single database whose tenant scope adds no constraint while no tenant is set, the job loads its rows by key across tenants.
- `Action` no longer uses `Queueable`, `Dispatchable` and `SerializesModels`: `onQueue()`, `onConnection()`, `delay()`, `dispatch()` and the `$connection`, `$queue` and `$delay` properties are removed, and a `$queue` or `$connection` property on an action no longer routes its jobs. Put Laravel's queue attributes on the action, or route the action class with `Queue::route()`:

  ```php
  use Illuminate\Queue\Attributes\Queue;
  use Illuminate\Queue\Attributes\Tries;
  use Lomkit\Rest\Actions\ActionFields;

  #[Queue('exports')]
  #[Tries(3)]
  class ExportOrders extends Action implements ShouldQueue
  {
      public function handle(ActionFields $fields, Collection $models): void
      {
          // Runs in the worker as the user who sent the request.
      }
  }
  ```

  ```php
  Queue::route(ExportOrders::class, 'exports', 'redis');
  ```

  `#[Connection]`, `#[Queue]`, `#[Delay]`, `#[Tries]`, `#[Timeout]`, `#[Backoff]`, `#[MaxExceptions]` and `#[FailOnTimeout]` apply to the jobs of the action. An attribute wins over a route. A batched action sends its batch to the same connection and queue.
- `release()`, `attempts()`, `fail()`, `delete()` and `batch()` work in `handle()`: the worker gives the action its job and its batch.
- An action that implements `Illuminate\Contracts\Queue\ShouldBeEncrypted` gets its queued payload encrypted, input values included.
- The job class is `Lomkit\Rest\Actions\QueuedAction`, and `Lomkit\Rest\Actions\CallRestApiAction` is removed. In tests, assert `Queue::assertPushed(QueuedAction::class)`. The job shows the action class as its name.
- Run the queued actions of the previous version before you deploy this one: a job queued before the upgrade cannot run after it.
- A resource and its `RestContext` can no longer be serialized. Do not put a resource, an action or a context in a job of your own: pass the class names and the keys.
- `Lomkit\Rest\Actions\ActionTarget` sets the rows that an action runs on. It replaces the `$standalone` and `$targeted` properties and the `standalone()`, `targeted()`, `isStandalone()` and `isTargeted()` methods:

  | Target | Request | Rows | Before |
  |---|---|---|---|
  | `ActionTarget::Search`, the default | `search` is optional, `resources` is refused | the rows that `search` matches inside `perimeterQuery()`, every row when the request sends no `search`, at most `$maxResources` | neither `standalone` nor `targeted` |
  | `ActionTarget::Resources` | `resources` is required, `search` is refused | the rows of the keys in `resources`, at most `$maxResources` | `targeted()` |
  | `ActionTarget::None` | `search` and `resources` are refused | no row: `handle()` receives an empty collection | `standalone()` |

  Set the target as a property of the action, or when the resource declares the action:

  ```php
  use Lomkit\Rest\Actions\ActionTarget;

  class DeactivateUsers extends Action
  {
      public ActionTarget $target = ActionTarget::Resources;
  }
  ```

  ```php
  public function actions(): array
  {
      return [SendDigest::make()->target(ActionTarget::None)];
  }
  ```

- Replace `public bool $standalone = true` and `->standalone()` with `public ActionTarget $target = ActionTarget::None` and `->target(ActionTarget::None)`. Replace `public bool $targeted = true` and `->targeted()` with `ActionTarget::Resources`. Replace `$action->isStandalone()` and `$action->isTargeted()` with `$action->target === ActionTarget::None` and `$action->target === ActionTarget::Resources`.
- Search your actions for `standalone` and `targeted` before you deploy. A `$standalone` or `$targeted` property that stays on an action is no longer read: the action then has the `Search` target, and a request without `search` runs it on every row that `perimeterQuery()` returns. A call to `standalone()` or `targeted()` fails with "Call to undefined method".
- `Lomkit\Rest\Exceptions\InvalidActionStateException` is removed. An action has one target.
- In the details response, each action entry gives `target`, `"search"`, `"resources"` or `"none"`, in place of `standalone` and `targeted`:

  ```json
  {"name": "Deactivate Users", "uriKey": "deactivate-users", "target": "resources", "fields": [], "meta": {}, "destructive": false, "confirm": null}
  ```

  Scramble documents `target` as a string with these three values.
- `php artisan rest:action` generates an action without a target: it has the `Search` target.
- `handle()` and `withBatch()` receive a `Lomkit\Rest\Actions\ActionFields` instead of an array, and so does a queued action in the worker. PHP refuses to load an action whose `handle()` or `withBatch()` still declares `array $fields`, and the error names the method:

  | Before | Now |
  |---|---|
  | `handle(array $fields, Collection $models)` | `handle(ActionFields $fields, Collection $models)` |
  | `withBatch(array $fields, PendingBatch $batch)` | `withBatch(ActionFields $fields, PendingBatch $batch)` |
  | `$fields['plan']` | `$fields->plan` |
  | `$fields['reason'] ?? null` | `$fields->reason` |
  | `array_key_exists('reason', $fields)` | `$fields->has('reason')` |
  | `isset($fields['reason'])` | `$fields->filled('reason')` |
  | `$fields` as an array | `$fields->toArray()` |

  ```php
  use Lomkit\Rest\Actions\ActionFields;

  public function handle(ActionFields $fields, Collection $models): void
  {
      foreach ($models as $subscription) {
          $subscription->update(['expires_at' => $fields->expires_at, 'plan' => $fields->plan]);
      }
  }
  ```
- Each input that `fields()` declares is a property, cast to its field type, with its `default()` filled in: a `Date` is a `CarbonImmutable`, an `Enum` the case. An input that the client did not send and that has no default is `null`. `has('reason')` is true when the client sent the input, `null` included, or when its field declares `default()`. `filled('reason')` is true when `has()` is true and the value is not `null`, and so is `isset($fields->reason)`. `only('plan', 'reason')` and `toArray()` return the inputs that `has()` reports, by name, in the order of `fields()`.
- Reading an input that `fields()` does not declare throws `Lomkit\Rest\Exceptions\InvalidFieldException`, a `LogicException`, also through `isset()`, `has()`, `filled()` and `only()`: "App\Rest\Actions\ExtendSubscriptions::fields() does not declare the input [seat].". Before, `$fields['seat'] ?? null` read `null`. Check the actions that extend another action and declare their own `fields()`: the `handle()` that they inherit must read only their inputs.
- `$fields->user()` returns the user who sent the request, `null` for a guest, also in the worker, and `$fields->target()` the `ActionTarget` of the action. `ActionFields` is read only.
- `ActionFields` can be serialized, for example to pass it to a job of your own. It keeps the cast values, the target and a reference to the user, and finds the user again through the user provider of the guard when it is unserialized. When the provider no longer finds the user, `unserialize()` throws `QueuedActionException`.
- `OperateRequest::resolveFields()` is removed. `handle()` receives the values in its `ActionFields`, and `$request->payload()->fields` keeps the input values of the request, see [Validation engine](#validation-engine).
- Instructions: `handle(InstructionFields $fields, Builder $query)` and `AppliesToEngine::handleEngine(InstructionFields $fields, EngineQuery $query)`. `Lomkit\Rest\Instructions\InstructionFields` reads the inputs as `ActionFields` does, without `user()` and `target()`; both extend `Lomkit\Rest\Fields\FieldValues`. Scope parameters do not change: the model scope still receives the cast values as arguments.
- `impacted` in the response of `operate` is the number of rows that the action ran on: the rows given to `handle()`, or the rows put into the jobs of a queued or batched action. Before, an action whose `search` sent a `limit` answered that limit, even when the search matched fewer rows: `{"search": {"limit": 50}}` on 11 rows answered `"impacted": 50`, and now answers `11`. A queued action counts the rows of its jobs when the request ends: the worker can later find fewer rows, see above.
- An action can answer with a result. Override `respond()` and return a `Lomkit\Rest\Actions\ActionResponse`:

  ```php
  use Lomkit\Rest\Actions\ActionFields;
  use Lomkit\Rest\Actions\ActionResponse;

  public function respond(ActionFields $fields, int $impacted): ?ActionResponse
  {
      return ActionResponse::message("{$impacted} subscriptions extended");
  }
  ```

  `respond()` runs once, in the request, after the last chunk: after `handle()` ran on every chunk, or after the jobs or the batch of a queued action were sent. It receives the `ActionFields` that `handle()` received and `impacted`. It does not run when `handle()` throws. It returns `null` by default.
- The response of `operate` gives `result`, `null` when `respond()` returns `null`:

  ```json
  {"data": {"impacted": 11, "result": {"type": "message", "message": "11 subscriptions extended"}}}
  ```

  | `respond()` returns | `result` |
  |---|---|
  | `ActionResponse::message('Done')` | `{"type": "message", "message": "Done"}` |
  | `ActionResponse::danger('2 subscriptions are locked')` | `{"type": "danger", "message": "2 subscriptions are locked"}` |
  | `ActionResponse::download($url, 'subscriptions.csv')` | `{"type": "download", "url": "...", "name": "subscriptions.csv"}` |
  | `ActionResponse::redirect($url)` | `{"type": "redirect", "url": "..."}` |
  | `ActionResponse::created($invoices)` | `{"type": "created", "keys": [29, 30]}` |
  | `ActionResponse::data(['export' => 41, 'rows' => 120])` | `{"type": "data", "data": {"export": 41, "rows": 120}}` |
  | `ActionResponse::message('Export started')->withData(['export' => 41])` | `{"type": "message", "message": "Export started", "data": {"export": 41}}` |
  | `null` | `null` |

  The status stays 200. The package does not send the file or follow the URL: the client acts on the result. `created()` takes the models, in a collection or an array, and gives their keys in that order; read the rows with a search. `data()`, and `withData()` on every type, take an array with string keys and give it as a JSON object, `{}` when empty; a list throws `Lomkit\Rest\Exceptions\InvalidActionException`.
- Clients that compare the whole response body of `operate` now also receive `result`.
- `->destructive()` and `->confirm('Extend the selected subscriptions?')` mark an action for clients, when the resource declares it or as the `$destructive` and `$confirm` properties of the action. The details response gives both in each action entry, after `meta`: `"destructive": false` and `"confirm": null` by default. Scramble documents both. The endpoint runs the action the same way. Each entry also gives `authorized`, see [Details](#details).
- `Action::handleRequest()` returns `Lomkit\Rest\Http\OperateResponse`, which holds `impacted` and `result`, instead of the count, and the `operate()` method of the controller returns that `OperateResponse`. A class that overrides one of them declares the new return type: PHP refuses to load an override that keeps `int` or `Illuminate\Http\Response`, and names the method.
- An action can check a policy ability on each row it runs on. Return the ability from `authorizeEach()`:

  ```php
  public function authorizeEach(): ?string
  {
      return 'update';
  }
  ```

  The action checks the ability through the resource's `authorizedTo()` on every row before it runs on any row: it reads the rows chunk by chunk, checks them, then reads them again to run. A refused row gives the status and the message of the first refusal, `{"message": "Closed tickets are read-only."}`, and `AuthorizationDenied` holds every refused row, at most 50, under `resources.<index>` for the `Resources` target or `search` for the `Search` target. No row is handled, no job is queued and no batch is created. `authorizeEach()` returns `null` by default: no row is checked.
- With `->skipUnauthorized()` when the resource declares the action, or `public bool $skipUnauthorized = true`, the action runs on the allowed rows only: each chunk is checked just before it is handled or queued, a refused row is left out, and `impacted` counts the rows handled. A chunk without an allowed row is not handled.
- The rows are checked in the request. A queued action does not check them again in the worker: call `$this->resource->authorizedTo('update', $model)` in `handle()` to check the rows as they are when the job runs.
- A row is checked as the request reads it. A search with `sorts` or a `limit`, and the `Resources` target, run the action on the rows whose keys were checked: a row that enters the search after the checks is not given to the action. A search without `sorts` and without `limit` reads its rows again after the checks: a row that enters the search in between is given to the action without a check. A row that changes after its check is not checked again.
- An action that calls `skipUnauthorized()` without an ability in `authorizeEach()`, or returns an ability on the `None` target, throws `Lomkit\Rest\Exceptions\InvalidActionException` when the resource's actions are read, for example on the details endpoint.
- An action that already defines `authorizeEach()`, `skipUnauthorized()` or `ensureValid()`, or a `$skipUnauthorized` property, gives them these signatures and this meaning.
- An action reads its rows in chunks that stay in place when the action changes them. Without `sorts` and without `limit` in its `search`, it reads the rows in the order of their key, from the lowest, each chunk after the last key of the previous one. With `sorts` or a `limit`, it reads the keys of the rows first, in the order of the search, then loads each chunk of keys through the same search. Before, it read pages of `chunkCount` rows by offset, so an action that changed a column that its search filters on skipped rows: a search on `status` `open` that closed 1,000 open tickets closed 500 of them. It now closes 1,000.
- The chunks of a search without `sorts` and without `limit` come in the order of the key, from the lowest, and no longer in the order of `defaultOrderBy()`. Add `sorts` to choose the order.
- A row that no longer matches the search when its chunk is loaded is not given to the action, and `impacted` does not count it.
- An action with the `Resources` target gives `handle()` its rows in the order of `resources`, in chunks of `chunkCount` keys. Before, they came in the order of `defaultOrderBy()`.
- Every target is capped by the `$maxResources` property of the action, 1000 by default. An action whose `search` matches more rows answers 422 under `search` before it runs on any row: "This action can be run on at most 1000 resources; narrow the search.". Before, only the `Resources` target and a text search on a search engine were capped. A search without `sorts` and without `limit` counts its rows first; with `sorts` or a `limit`, the action reads at most `$maxResources` + 1 keys. Raise the cap on an action that runs on more rows, or set it to `null` to remove it: the action then runs on every row that its search matches or that `resources` names, and does not count them first. A redeclared property takes the `?int` type:

  ```php
  public ?int $maxResources = 5000;
  ```

  `->maxResources(5000)` or `->maxResources(null)` sets it when the resource declares the action.
- Each chunk of an action runs in a database transaction on the connection of the model of the resource, in the request or, for a queued action, around `handle()` in the worker. Before, a synchronous chunk ran on the default connection, so the rows of a model on another connection were written outside it, and a queued job ran without a transaction: when `handle()` throws on row 50 of a job, rows 1 to 49 of that job are now rolled back. Call `->withoutTransaction()` on a queued action that must keep them.
- The `$transaction` property of the action chooses its transactions: `ActionTransaction::Chunk`, the default, gives each chunk its own; `ActionTransaction::Whole`, or `->atomic()` when the resource declares the action, runs every chunk in one, so that when `handle()` throws on a chunk, the rows of every chunk are rolled back; `ActionTransaction::None`, or `->withoutTransaction()`, opens none, so that each row is written when `handle()` writes it, for an action that calls an external service on each row or commits its own progress. Without `Whole`, the rows written before a failure stay written and the request answers with the error. `respond()` runs after the commit.

  ```php
  use Lomkit\Rest\Actions\ActionTransaction;

  public ActionTransaction $transaction = ActionTransaction::None;
  ```

  An action that implements `ShouldQueue` and runs with `Whole` throws `Lomkit\Rest\Exceptions\InvalidActionException` when the resource's actions are read: "App\Rest\Actions\ExportOrders runs its chunks in one transaction (ActionTransaction::Whole), but it implements ShouldQueue: its chunks run in the worker, outside the request."
- An action that already defines `atomic()`, `withoutTransaction()` or a `$transaction` property gives them this signature and this meaning.
- A batched action (`BatchableAction`) gives the id of its batch in the response, between `impacted` and `result`:

  ```json
  {"data": {"impacted": 250, "batch": "9f0c4d2e-5a1b-4c3d-8e7f-0a1b2c3d4e5f", "result": null}}
  ```

  Read the batch with `Bus::findBatch($id)`. The batch keeps the resource, the action and the user who started it in its options, under `lomkit_rest_owner`.
- `withBatchProgress()` on `Rest::resource()` adds `GET {name}/actions/{action}/batches/{batch}`, named `{name}.batch`:

  ```php
  Rest::resource('invoices', InvoiceController::class)->withBatchProgress();
  ```

  It answers the user who started the batch, through the same guard:

  ```json
  {"data": {"id": "9f0c4d2e-5a1b-4c3d-8e7f-0a1b2c3d4e5f", "total_jobs": 3, "pending_jobs": 1, "failed_jobs": 0, "progress": 67, "finished": false, "cancelled": false}}
  ```

  Another user, a guest, another action and an unknown batch get a 404, and so does every batch started by a guest. The route checks the abilities that the resource's `abilities()` returns for `Endpoint::Batch`, none by default.
- The jobs of a batched action skip their rows once the batch is cancelled, with Laravel's `SkipIfBatchCancelled` middleware: `$this->batch()?->cancel()` in `handle()`, or `Bus::findBatch($id)?->cancel()`, stops the jobs that did not run yet.
- `DispatchAction::dispatch()` returns the `OperateResponse` of the action, without `result`; `OperateResponse::answer()` gives a copy with the result.
- An action can carry its own route middleware. Add it when the resource declares the action, or as properties of the action:

  ```php
  public function actions(): array
  {
      return [
          ExportTickets::make()->middleware('throttle:exports'),
          ReportOutage::make()->withoutMiddleware('auth:sanctum'),
      ];
  }
  ```

  ```php
  class ExportTickets extends Action
  {
      public array $middleware = ['throttle:exports'];
  }
  ```

  The route of the action gets the package middleware, the middleware of the resource, the middleware of `middlewareFor('operate', ...)`, then its own. `withoutMiddleware()` removes a middleware of any of these, of the route group and of the controller. Name the middleware as it is declared, with its parameters: `withoutMiddleware('auth:sanctum')` for `->middleware('auth:sanctum')`. An action whose `abilities()` returns `[]` and that removes the authentication middleware runs for guests. `middlewareFor('operate', ...)` and `withoutMiddlewareFor('operate', ...)` still apply to every action.
- An action that already defines `middleware()` or `withoutMiddleware()`, or a `$middleware` or `$withoutMiddleware` property, gives them these signatures and this meaning.

### Events

The controller hooks are removed. The resource has endpoint events instead: public methods that receive a typed payload and run at fixed moments. Override them on the resource:

| Controller hook (removed) | Resource event | When it runs |
|---|---|---|
| `beforeDetails()` | `describing(): void` | once `viewAny` passed, before the details are built |
| (none) | `described(array $details): void` | after the details are built |
| `beforeSearch()` | `searching(SearchContext $search): void` | after validation, before any query; also for the search of an action or a metric |
| `afterSearch()` | `searched(LengthAwarePaginator $page): void` | after the query and the includes are read, before the rows are rendered |
| `beforeMutate()` | `mutatingAll(array $entries): void` | inside the transaction, after the row checks, before the first write |
| `afterMutate()` | `mutatedAll(array $operations): void` | inside the transaction, after the last write; the `response` block is read after the commit |
| `beforeOperate()` | `operating(Action $action, ActionFields $fields): void` | after the row checks of `authorizeEach()`, before the first chunk; inside the transaction of an action with `ActionTransaction::Whole` (`atomic()`) |
| `afterOperate()` | `operated(Action $action, ActionFields $fields, int $impacted): void` | after the last chunk, before `respond()`; inside the transaction of an action with `ActionTransaction::Whole` (`atomic()`) |
| `beforeDestroy()`, `beforeRestore()`, `beforeForceDestroy()` | `destroyingAll(Collection $models)`, `restoringAll()`, `forceDestroyingAll()` | inside the transaction, after the row checks, before the first row |
| `afterDestroy()`, `afterRestore()`, `afterForceDestroy()` | `destroyedAll(Collection $models)`, `restoredAll()`, `forceDestroyedAll()` | inside the transaction, after the last row |

- Move the code of each hook to its event on the resource, and delete the hook. PHP refuses to load a controller that marks a removed hook with `#[\Override]`, and names the method; a hook without the attribute is no longer called. The controller keeps `$resource` only.
- The events run after the entry checks and the validation. The events of `mutate`, `operate`, `destroy`, `restore` and `forceDestroy` also run after the checks of each row: where `beforeMutate()` or `beforeDestroy()` ran before them, their events run after them, and a row that a policy refuses runs no event. `searching()` runs before any row is read.
- An event that throws stops the endpoint. A `ValidationException` gives a 422 and an `AuthorizationException` a 403. Inside a transaction, the throw rolls it back: `mutatedAll()` and `destroyedAll()` can undo the write. Before, `afterMutate()` and `afterDestroy()` ran after the commit. An action without `ActionTransaction::Whole` commits each chunk, or writes each row at once with `ActionTransaction::None`: a throw in `operated()` leaves the rows written and skips `respond()`. Jobs of a queued action are pushed when their chunk is read; a throw in `operated()` does not recall them, and a batch is not dispatched.
- `searched()` receives the page that the response renders. Before, `afterSearch()` ran before the query. `described()` is new: the controller had no hook after the details.
- An event cannot change what the endpoint does: it returns nothing, and its payload is read-only. Do not change the rows, the page or the operations it receives. Read the request with `$this->request()`: it is the request of the endpoint. An edit of its input changes nothing.
- `mutatingAll()` receives the entries of the mutation, each a `Lomkit\Rest\Mutations\MutationNode` with its `operation`, `key`, `attributes` and `relations`. `mutatedAll()` receives the operations that the response returns under `operations`.
- Refuse a search that combines filters in `searching()`. The check also applies to the search of an action, before any row is read, and to the search of a metric:

  ```php
  use Illuminate\Validation\ValidationException;
  use Lomkit\Rest\Search\SearchContext;

  public function searching(SearchContext $search): void
  {
      if ($search->filtersOn('from') && ! $search->filtersOn('to')) {
          throw ValidationException::withMessages(['search.filters' => 'The from filter requires a to filter.']);
      }
  }
  ```

- Log a deletion:

  ```php
  use Illuminate\Database\Eloquent\Collection;

  public function destroyedAll(Collection $tickets): void
  {
      ActivityLog::record('tickets.destroyed', $tickets->modelKeys());
  }
  ```

- The resource now declares `describing()`, `described()`, `searching()`, `searched()`, `mutatingAll()`, `mutatedAll()`, `operating()`, `operated()`, `destroyingAll()`, `destroyedAll()`, `restoringAll()`, `restoredAll()`, `forceDestroyingAll()` and `forceDestroyedAll()`. A resource method with one of these names and another signature no longer loads: rename it.
- The queued jobs of an action run no event: the events run in the request.

Laravel events:

- After each write, the package dispatches a Laravel event through the event dispatcher, for every resource. Listen to it with `Event::listen()` in place of a resource event that only reports the write. `resource` is the class of the resource:

  | Event (`Lomkit\Rest\Events`) | Endpoint | Properties |
  |---|---|---|
  | `ResourceMutated` | `mutate` | `resource`, `operations` (the `operations` of the response, as arrays) |
  | `ResourceDestroyed`, `ResourceForceDestroyed` | `destroy`, `forceDestroy` | `resource`, `model` (the class of the model), `keys` (the keys of the rows, in the order of `resources`) |
  | `ResourceRestored` | `restore` | `resource`, `models` (the `Collection` that `restoredAll()` receives) |
  | `ActionOperated` | `operate` | `resource`, `action` (the class of the action), `uriKey`, `fields` (the values of the fields, by name), `impacted`, `batch` (the id of the batch, or `null`) |

- The event is dispatched once the write is committed, once per request: never for a write that rolls back, nor for an attempt that a deadlock runs again. The events implement `ShouldDispatchAfterCommit`: inside a transaction of your application, they wait for its commit, and a rollback drops them. A listener cannot refuse or change the write: refuse it in the resource events.
- `ActionOperated` is dispatched once the run ends without error: after the last chunk, after the commit of `ActionTransaction::Whole`, and, for a queued action, in the request once the jobs are queued and the batch is dispatched. A run that throws dispatches nothing, even when earlier chunks stay committed. The queued jobs dispatch no event. Searches, details and metrics dispatch none.
- A listener filters by resource: `$event->resource === TicketResource::class`. Log every deletion:

  ```php
  use Illuminate\Support\Facades\Event;
  use Lomkit\Rest\Events\ResourceDestroyed;

  Event::listen(ResourceDestroyed::class, fn (ResourceDestroyed $event) => ActivityLog::record($event->resource, 'destroyed', $event->keys));
  ```

- A listener may implement `ShouldQueue`. The events serialize: `ResourceRestored` uses `SerializesModels`, so the worker reloads its rows. `ResourceDestroyed` and `ResourceForceDestroyed` carry keys, since a deleted row may no longer load.
- A resource method that already dispatches its own event for these writes now runs next to the package event: remove one of the two.

Hooks of each row and the perimeter:

- The hooks of each row, the `perform*()` methods, `perimeterQuery()` and the closure of `ScoutEngine::perimeter()` no longer take the request. Read it with `$this->request()`: it is the request of the endpoint, such as `DestroyRequest`, also in the hooks and the perimeter of a nested entry or an include, and in the worker of a queued action. Remove the request parameter from each override, and the argument of its `parent::` call. PHP refuses to load a class whose override keeps it, and names the method. A perimeter closure that keeps it throws an `ArgumentCountError` when a text search runs.

  | Before | Now |
  |---|---|
  | `mutating(MutateRequest $request, array $requestBody, Model $model)` | `mutating(MutationNode $entry, Model $model)` |
  | `mutated(MutateRequest $request, array $requestBody, Model $model)` | `mutated(MutationNode $entry, Model $model)` |
  | `destroying(DestroyRequest $request, Model $model)`, `destroyed()` | `destroying(Model $model)`, `destroyed(Model $model)` |
  | `restoring(RestoreRequest $request, Model $model)`, `restored()` | `restoring(Model $model)`, `restored(Model $model)` |
  | `forceDestroying(ForceDestroyRequest $request, Model $model)`, `forceDestroyed()` | `forceDestroying(Model $model)`, `forceDestroyed(Model $model)` |
  | `performDelete(RestRequest $request, Model $model)`, `performRestore()`, `performForceDelete()` | `performDelete(Model $model)`, `performRestore(Model $model)`, `performForceDelete(Model $model)` |
  | `perimeterQuery(RestRequest $request, Builder $query): Builder` | `perimeterQuery(Builder $query): Builder` |
  | `ScoutEngine::make()->perimeter(fn (EngineQuery $query, RestRequest $request) => ...)` | `ScoutEngine::make()->perimeter(fn (EngineQuery $query) => ...)` |

- `mutating()` and `mutated()` receive the entry as a `Lomkit\Rest\Mutations\MutationNode`, the node of `$request->payload()` itself, instead of an array: `operation` (a `Lomkit\Rest\Fields\MutationOperation`), `key` (`null` on `create`, a list for an entry that sends several keys), `attributes`, `pivot`, `withoutDetaching`, `relations` (the nodes of each relation that the entry sends), `type` (the morph type of a `MorphTo` entry), `depth` and `path`. Replace `$requestBody['attributes']['title']` with `$entry->attributes['title']`, and `$requestBody['operation'] === 'create'` with `$entry->operation === MutationOperation::Create`. `MutationNode::toArray()` is removed.
- The hooks run at the same moments as before, inside the transaction of their endpoint.

  ```php
  use Illuminate\Contracts\Database\Eloquent\Builder;
  use Illuminate\Database\Eloquent\Model;
  use Lomkit\Rest\Fields\Integer;
  use Lomkit\Rest\Fields\MutationOperation;
  use Lomkit\Rest\Mutations\MutationNode;
  use Lomkit\Rest\Text\Engine\EngineQuery;
  use Lomkit\Rest\Text\ScoutEngine;
  use Lomkit\Rest\Text\TextSearch;

  public function perimeterQuery(Builder $query): Builder
  {
      return $query->where('team_id', $this->request()->user()->team_id);
  }

  public function text(): ?TextSearch
  {
      return ScoutEngine::make()->perimeter(
          fn (EngineQuery $query) => $query->where('team_id', '=', $this->request()->user()->team_id),
          [Integer::make('team_id')],
      );
  }

  public function mutating(MutationNode $entry, Model $model): void
  {
      if ($entry->operation === MutationOperation::Create) {
          $model->setAttribute('created_by', $this->request()->user()->id);
      }
  }
  ```

- The callbacks of `requiredOnCreation()` and the other relation constraints keep their request argument.

### Metrics

Metrics are new in 3.0. A resource declares them in `metrics()`, and each metric gets its own route, `POST {name}/metrics/{uriKey}`. A metric extends `Lomkit\Rest\Metrics\Metric`, names the type of its result in `type()` and computes it in `calculate()`. The query it receives holds the perimeter of the resource and the search of the request.

```php
use Illuminate\Database\Eloquent\Builder;
use Lomkit\Rest\Metrics\Metric;
use Lomkit\Rest\Metrics\MetricResult;

class TicketBacklog extends Metric
{
    public function type(): string
    {
        return 'gauge';
    }

    public function calculate(Builder $query): MetricResult
    {
        return new Gauge($query->where('status', 'open')->count(), 500);
    }
}

final readonly class Gauge implements MetricResult
{
    public function __construct(private int $value, private int $max) {}

    public function jsonSerialize(): array
    {
        return ['value' => $this->value, 'max' => $this->max];
    }
}

// TicketResource
public function metrics(): array
{
    return [TicketBacklog::make()];
}
```

`POST /api/tickets/metrics/ticket-backlog` with `{}` answers `{"data": {"type": "gauge", "value": 42, "max": 500}}`.

- The resource now declares `metrics()`. A resource method named `metrics()` with another signature no longer loads: rename it.
- Every resource gets `POST {name}/metrics/{metric}`, named `{name}.metric`, also without metrics. It answers the keys of no metric: `403` to a user that `viewAny` refuses, `404` to the others. `except('metric')` removes the metric routes. `php artisan route:list` and `rest:check` count one more route for each resource.
- The details response has a new `metrics` key, after `actions`: `[]` for a resource without metrics. Each entry ends with `authorized`, see [Details](#details).
- `Lomkit\Rest\Http\Routing\Endpoint` has a `Metric` case, after `Operate`. A `match` on `Endpoint` without a default arm handles it.

Three built-in types compute the usual results in one query: `Value`, `Partition` and `Progress`. They read the fields that the resource declares.

```php
use Illuminate\Database\Eloquent\Builder;
use Lomkit\Rest\Metrics\Value;
use Lomkit\Rest\Metrics\ValueResult;

class TotalRevenue extends Value
{
    public function calculate(Builder $query): ValueResult
    {
        return $this->sum($query, 'price', 'placed_at');
    }

    public function ranges(): array
    {
        return [30 => '30 days', 90 => '90 days'];
    }
}
```

`POST /api/orders/metrics/total-revenue` with `{}` answers `{"data": {"type": "value", "value": 52.0, "previous": 37.25}}`: the total of the last 30 days and of the 30 days before. `{"range": 90}` selects the second range. A `Partition` gives one part per value of a column, and `->limit(5)` folds all but the 5 largest parts into `other`. A `Progress` gives a value and its target: `$this->sum($query, 'price', target: 100000)`. A `Trend` gives one value per day, week or month, `$this->sumByMonths($query, 'price', 'placed_at')`, and its range keys count periods; `splitBy` adds one series per value of a column, and `->limit(5)` folds all but the 5 largest splits into `other`. Weeks start on Monday unless the metric declares `->weekStartsOn(Carbon::SUNDAY)`. A request can send `timezone` (`{"timezone": "Europe/Paris"}`) to count the periods of a trend in that zone; without it, the periods follow `config('app.timezone')`. A `Table` gives the top rows of a grouped aggregate, one column per count, sum, average, min or max: `$this->rows($query, 'customer', ['orders' => TableColumn::count(), 'revenue' => TableColumn::sum('price')], orderBy: 'revenue')`.

- A metric declares its ranges in `ranges()`, and the request picks one with `range`: the first one when it sends none. A key that the metric does not declare is a `422`, and so is `range` for a metric without ranges. The details entry of a metric lists them in `ranges`.
- The floats of a metric response keep their fraction: `52.0`, not `52`.
- `->cacheFor(600)` keeps the result of a metric between requests for 600 seconds; it also takes a `DateInterval` or an instant, `->cacheFor(now()->addMinutes(10))`. Each user gets their own result. `->cacheScope('global')` shares one result between the users whose `perimeterQuery()` compiles to the same SQL and bindings: the users of one team share it, and another team gets its own. The key holds the SQL of `perimeterQuery()` only: a perimeter that also depends on something else, such as a global scope of the model or one that it removes for some users, adds that to `authorizationCacheContext()`. With several tenants, add the tenant to it: with a database per tenant, `perimeterQuery()` compiles to the same SQL in every tenant, and the global scope of a single database is not part of the key, so without it the tenants share one global result, see "Cache" in [Authorization](#authorization). A metric class can declare `public ?int $cacheFor = 600;` and `public CacheScope $cacheScope = CacheScope::Global;` (`Lomkit\Rest\Metrics\CacheScope`) instead. The results go to the store of `authorizations.cache.store`, under the stamps of the decisions: `Rest::forgetAuthorizationsFor($user)` drops the results of a user, `Rest::forgetAuthorizations()` drops every result, and with `authorizations.cache.enabled` set to `false` no result is kept. See "Cache" in [Authorization](#authorization).
- `php artisan rest:metric` writes a metric of each built-in type, or of a type of your own: see [Generators](#generators).

### Native types

The members that applications redeclare or override declare native PHP types. PHP checks a subclass when it loads it: a redeclared property must keep the parent's type, and an overriding method must declare a compatible return type. A class that does not match stops with a fatal error.

Resources and controllers:

- `$model` and `$resource` are declared `public static string`. Redeclare them with the type:

  ```php
  class PostResource extends Resource
  {
      public static string $model = Post::class;
  }

  class PostsController extends Controller
  {
      public static string $resource = PostResource::class;
  }
  ```

- A resource without `$model` throws `Lomkit\Rest\Exceptions\InvalidResourceException`, and a controller without `$resource` throws `Lomkit\Rest\Exceptions\InvalidControllerException`. Both messages name your class. Read the values with `Resource::modelClass()` and `Controller::resourceClass()`, which throw these exceptions, instead of `static::$model` and `static::$resource`.
- `Rest::resource()` loads the controller class and checks its resource when the routes register. A controller that PHP cannot load, such as one that still declares an untyped `$resource`, now stops the route files and every Artisan command, not only its own endpoints. Routes served from the route cache skip this check: their controller throws on its first request instead.
- `rest:resource` and `rest:controller` generate the typed properties.

Query hooks:

- `searchQuery()` is renamed `perimeterQuery()`: it sets the rows that exist for the client on every endpoint, see "Rows that a write reaches" in [Writes](#writes). Rename each override and its `parent::` call. An override that keeps the old name is no longer called.
- `mutateQuery()`, `destroyQuery()`, `restoreQuery()` and `forceDeleteQuery()` are removed. Move the conditions of each override to a policy or to `perimeterQuery()`, as "Rows that a write reaches" in [Writes](#writes) describes, then delete it.
- PHP refuses to load a class that marks an override of a renamed or removed hook with `#[\Override]`, and names the method. Find the other overrides by name, including the five hooks that `rest:base-resource` wrote in `app/Rest/Resources/Resource.php` under 2.x: `rest:base-resource` no longer generates them, and a hook that only returns `$query` can be deleted.
- `perimeterQuery()` returns `Illuminate\Contracts\Database\Eloquent\Builder`. Add the return type to the override. The parameter keeps the contract type, and the return type may be the Eloquent `Builder` instead of the contract:

  ```php
  use Illuminate\Contracts\Database\Eloquent\Builder;

  public function perimeterQuery(Builder $query): Builder
  {
      return $query->where('team_id', $this->request()->user()->team_id);
  }
  ```

- An override must return the builder: one that changes `$query` and returns nothing now throws a `TypeError`. The package ignores the returned builder, so apply the constraints to `$query` itself, as before.
- `Lomkit\Rest\Relations\Relation::applySearchQuery()` is renamed `applyPerimeter()` and returns `void`. A custom relation that overrides it renames the override.

Responses:

- `$response` is declared `public static string`, and `newResponse()` returns `Lomkit\Rest\Http\Response`. The class it names must extend `Lomkit\Rest\Http\Response`, otherwise the endpoints throw a `TypeError`.

Validation rules:

- `rules()`, `createRules()` and `updateRules()` return `array`.

Actions, instructions and relations:

- `Action`, `Instruction` and `BatchableAction` declare native types:
  - a redeclared property keeps its type: `public ?string $name`, `public ActionTarget $target`, `public int $chunkCount`;
  - an override declares its return type: `handle(): void`, `withBatch(): void`, `name(): string`, `uriKey(): string`, `target(): static`, `destructive(): static`, `confirm(): static`, `respond(): ?ActionResponse`, `handleRequest(): OperateResponse`.
- `Lomkit\Rest\Actions\DispatchAction` is final and readonly. `dispatch()` takes no argument. `handleClassic()`, `handleTargeted()`, `handleStandalone()`, `dispatchInChunks()` and `modelsQuery()` are removed: the rows of each target come from a class of `Lomkit\Rest\Actions\Rows`, and `EngineTargets::dispatch()` is renamed `each()`. To change how an action runs, override `handleRequest()` on the action.
- An action's `handle()` returns `void`. Before, a synchronous action that returned `false` stopped the remaining chunks; the chunks now always run.
- `make()` returns `static`. An action or instruction that redefines it must declare `: static`. On relations, `make()` takes the arguments of the constructor: `make(string $relation, string $type)`, and `make(string $relation, string|array $types)` on `MorphTo`. A relation that redefines it declares them, and a relation whose constructor takes other arguments redefines `make()`: it no longer passes every argument to the constructor.
- Relations declare native types: `__construct(string $relation, string $type)`, `name(): string`, `hasMultipleEntries(): bool`, `resource(): Resource` and `rules(Resource $resource, string $prefix): array`, where `Resource` is `Lomkit\Rest\Http\Resource`. A relation that overrides one of them declares the same types.
- On relations, `requiredOnCreation()`, `prohibitedOnCreation()`, `requiredOnUpdate()` and `prohibitedOnUpdate()` return `static`. A relation that overrides one of them must declare `: static`. Their callbacks receive the request and the related resource, `null` on a `MorphTo` with several targets: `fn (RestRequest $request, ?Resource $resource): bool`. The request is the request of the resource that declares the relation.
- The public `relationRequiredOnCreationCallback`, `relationProhibitedOnCreationCallback`, `relationRequiredOnUpdateCallback` and `relationProhibitedOnUpdateCallback` properties of relations are removed: the constraints are now private. Set them with `requiredOnCreation()`, `prohibitedOnCreation()`, `requiredOnUpdate()` and `prohibitedOnUpdate()`, and read them with `isRequiredOnCreation()`, `isProhibitedOnCreation()`, `isRequiredOnUpdate()` and `isProhibitedOnUpdate()`, which take no argument.
- `resource(Resource $resource)` returns `static` on actions, instructions and requests.

Other resource methods:

- `newModel(): Model`.
- `instructionExists()` is removed. `$resource->definition()->instruction($key) !== null` gives the same answer.
- `Lomkit\Rest\Validation\KeysGrammar::of()` and `idOf()`, and `Lomkit\Rest\Text\TextTarget::of()`, no longer take the request.
- `performDelete()`, `performRestore()` and `performForceDelete()` return `void`.
- `paginate(Builder $query, int $perPage, int $page)` receives the query of the search, the page size and the page number, and returns `Illuminate\Contracts\Pagination\LengthAwarePaginator`. Read the size and the page from these parameters, not from the request: the package passes `search.limit`, or `$defaultLimit` when it is not sent, and `search.page`, or 1. The default `paginate()` runs the page query first and the count query only when the page is full, or empty after the first page: otherwise `total` is the number of rows before the page plus the rows of the page, and no count query runs. Before, the count ran first, on every search. An override that calls `$query->paginate()` keeps Laravel's count, which runs first; `(new Lomkit\Rest\Query\PageQuery($query, $this->definition()->limits()->maxTotal))->paginate($perPage, $page)` gives the default behaviour.

Controller endpoints:

- `details()` returns `array`, `mutate()` returns `Lomkit\Rest\Http\MutateResponse`, `operate()` returns `Lomkit\Rest\Http\OperateResponse`, and `search()`, `destroy()`, `restore()` and `forceDestroy()` return `Lomkit\Rest\Http\Response`.

Requests:

- `authorize()` returns `bool|Illuminate\Auth\Access\Response`, so it can still return `Response::deny()`.
- `rules()` returns `array`.
- `Lomkit\Rest\Http\Requests\ActionsRequest` is removed. Nothing used it: the operate hooks receive an `OperateRequest`.

Service provider and facade:

- `RestServiceProvider::register()` and `boot()` return `void`, and the `Rest` facade's `getFacadeAccessor()` returns `string`.

### Search

Text search (`search.text`). A resource now declares its text search with `text()`, see [Text search](#text-search) and [Search engines](#search-engines):

- A request runs a text search when `search.text.value` is filled. A blank, `null` or missing value runs the normal search and returns the normal list, and the rest of the payload is validated as a normal search. Before, a blank or `null` value returned an empty page.
- `search.text.value` accepts at most 255 characters.
- `search.text.trashed` is removed and gives a 422. Send `search.trashed` instead, see [Text search](#text-search).
- `viewAny` is checked before any search runs, including a text search without hits and a page past the last one.
- Includes and aggregates are validated as in a search without text, also next to a text search, because they run in SQL: their filters, sorts, selects and scopes use the fields and operators of the related resource. An include or an aggregate whose `relation` is missing or not declared gives a 422.
- The actions of a resource that does not declare `text()` refuse `search.text` with a 422 under `search.text`: "The search.text field is prohibited.", as on the search endpoint. The actions of a resource that declares it act on the matching rows, see [Text search](#text-search).

Validation:

- `search.page` must be an integer of at least 1, with or without text. Before, a page below 1 was accepted.
- `search.aggregates.*.type` is required, and `search.aggregates.*.alias` must be a string when it is sent. A `null` alias gives a 422, "The search.aggregates.0.alias field must be a string.": leave the key out to keep the default name.
- An include or an aggregate whose `relation` is a dotted path with an unknown first relation (`suppliers.country`) gives a 422 under its `relation` key.
- A filter whose `field` is not a string gives a 422 under its `field` key, in `search.filters`, in nested filters and in the filters of includes and aggregates.
- On an action, `search` must be an object (a JSON array of keys and values) when it is sent. It no longer accepts `page`: "The search.page field is prohibited." Before, `page` was validated and then ignored, and with `limit` the action acted on the first rows of the search.
- A name repeated in the `fields` of an action or an instruction gives a 422 under each repeated entry: `fields.{index}.name` for an action, `search.instructions.{index}.fields.{position}.name` for an instruction. Before, the last value won.
- Each entry of `resources` on `destroy`, `restore` and `forceDestroy` must be a key typed like the resource's `ID` field: an integer for integer keys, and a string, a UUID or a ULID for the other key types (a resource without an `ID` field uses the model's key type). A list, an object or `null` gives a 422 under `resources.{index}`. The other checks of `resources` are listed under "Resource keys" in [Validation engine](#validation-engine).
- In `mutate`, an `attributes`, `relations` or `pivot` value that is not an object gives one 422 message under its key.
- The checks of field, scope, instruction and action field names use a new rule, `Lomkit\Rest\Rules\OneOf`, instead of `Rule::in()`. The message is still `validation.in` ("The selected :attribute is invalid."), but a custom message written for the `in` rule of these attributes no longer applies. A sort field is checked by `Lomkit\Rest\Validation\Search\SortPaths`, which accepts the names of a `OneOf` and checks a `<relation>.<field>` path against its own relation: `SearchRequest::rules()` holds it under `search.sorts.<i>.field`. Both implement `Lomkit\Rest\Rules\AllowedValues`, which `SearchGrammar::sortFields()` returns.

Limits:

- Each include returns at most a set number of rows for each parent row: its `limit`, or else the `$includeLimit` of the related resource, 10 by default. Before, an include without `limit` returned every related row. The rows kept are the first ones in the order of the include: its `sorts`, or else the `defaultOrderBy()` of its resource. This applies to every include other than `BelongsTo` and `MorphTo` (below), `HasOne` and the `*OfMany` relations included, at every level, aliased or not, in a search, in the search of an action, next to a text search on an engine, and in the `response` block of a mutation. An include that needs more rows sends a `limit` that the `limits()` of its resource holds, or the resource sets a higher `$includeLimit`, for example on the base resource class of the application:

  ```php
  public int $includeLimit = 25;
  ```

- A row whose level includes a to-many relation (`HasMany`, `MorphMany`, `BelongsToMany`, `MorphToMany`, `MorphedByMany` or `HasManyThrough`) ends with `@more`, the keys of those includes that have more rows than they returned, `[]` when none: `{"id": 1, "name": "Billing", "tickets": [...], "@more": ["tickets"]}`. Read it to tell a client that a list is not complete. Each such include reads one more row for each parent and drops it before `searched()`, the gates and `map()`. `search.more_key` in `config/rest.php` renames the key: a resource that declares a field named like it throws `Lomkit\Rest\Exceptions\InvalidResourceException`, and an include or aggregate alias named like it gives a 422.
- The relation that a dotted include goes through, such as `posts` in `posts.comments`, is limited the same way: each parent row keeps the first `$includeLimit` rows of the relation in the `defaultOrderBy()` of its resource, and the rows below come from those. Before, it loaded every row, in no set order. List `posts` as an include of its own, with its `limit` and `sorts`, to choose its rows.
- `$defaultLimit` and `$includeLimit` must be values that `limits()` returns. When one is not, the first read of the limits of the resource in a request (a search, an action, the details) throws `Lomkit\Rest\Exceptions\InvalidResourceException`: `The resource [App\Rest\Resources\PostResource] sets $defaultLimit to 32, which is not one of its limits() [10, 25, 50]. Return 32 from limits(), or set $defaultLimit to one of them.` Before, a `$defaultLimit` outside `limits()` sized the page. The default `limits()`, `[10, 25, 50]`, holds both defaults; a resource that returns other values from `limits()` returns 10 there or sets its `$includeLimit`.
- A `BelongsTo` or `MorphTo` include loads the one related row of each parent row, with no limit and no order. `limit` and `sorts` there give a 422 under each key: `The search.includes.0.limit field is prohibited: this relation loads at most one record per parent.` Before, the `limit` of a `BelongsTo` include applied to the query of all the parent rows, so some rows returned `null` instead of their related row, and its `sorts` changed nothing in the response. `BelongsTo` and `MorphTo` implement `Lomkit\Rest\Contracts\OwnerRelation`, and a relation class that extends them keeps it. In a `MorphTo` type block, `limit` and `sorts` give the same 422.
- Aggregates count every related row, the rows of an action keep the `limit` of its search, without a default, and the records of a mutation hold one row per entry.
- A resource over a large table can cap the count of its searches with `public ?int $maxTotal = 10000;`: the count reads at most 10,001 rows, without the order of the search. When more rows match, a page answers `"total": 10000` and `"total_capped": true` after `total`, and `last_page` follows that total (200 with `per_page` 50); otherwise it answers the exact `total` and `"total_capped": false`. A page past the capped total still answers its rows, and its `total` is at least the rows before it plus its own. `null`, the default, counts every row and leaves `total_capped` out. `$maxTotal` must be at least the largest value of `limits()`, or the first read of the limits throws `InvalidResourceException`: `The resource [App\Rest\Resources\LogResource] sets $maxTotal to 20, which is below its largest limit 50. Set $maxTotal to 50 or more, or to null to count every row.` A text search on a search engine keeps the total of the engine.

Order:

- Every SQL search now ends its order with the model key, in descending order, unless the order already holds the key: `"sorts": [{"field": "status"}]` runs `order by tickets.status asc, tickets.id desc`. This applies to `sorts`, `defaultOrderBy()`, the relevance order, every include level and the rows of a sorted action, so rows with equal sort values come in one fixed order and no longer appear on two pages or on none. To order ties by another column, add that column to `sorts` or to `defaultOrderBy()`.

Query builder and requests:

- `Lomkit\Rest\Query\Builder`, the `Lomkit\Rest\Contracts\QueryBuilder` contract, the `QueryBuilder` binding and the `Lomkit\Rest\Query\Traits\PerformSearch` trait are removed. The package builds each search from the query of the model and applies the `Lomkit\Rest\Search\Plan\QueryPlan` that it builds from `$request->payload()`; it no longer resolves a query builder from the container. Move the logic of a custom binding, or of a class that extends `Builder`, to the resource or the model: limit the rows of a resource in `perimeterQuery()`, limit the rows of a model with a global scope of the model, which also applies to the rows of a search engine page, or refuse a search in `searching()`, see [Events](#events). `scopes()`, `instructions()`, `defaultOrderBy()` and `paginate()` change a search too.
- `disableSecurity()` is removed with `Builder`. `perimeterQuery()` applies to every SQL search; the rows of a search engine page come from the index, see [Search engines](#search-engines).
- `RestRequest::isScoutMode()` is removed. `$request->context()->text($resource)` returns the text search of the resource for the request, a `Lomkit\Rest\Text\ResolvedText`: its `usesEngine()` is true when the request runs its text on a search engine, see [Search engines](#search-engines), and its `query` holds the text of the search, or `null` when the request has no text to search.
- The search parser validates `text` and `trashed` at the root of the search, and an include refuses them: "The search.includes.0.text field is prohibited."
- `$resource->definition()->relation()` returns `null` for a dotted path whose first relation is not declared. Before, it failed with an error.
- `Lomkit\Rest\Search\Plan\RowSource` and `KeySet` gain `perimeter(Resource $resource): array`, the clauses that limit the first level of a plan: `SqlRows` and the keys of the `response` block of a mutation return the `perimeterQuery()` of the resource, the keys of a search engine page return none. A class that implements one of them adds the method.
- The clauses of `Lomkit\Rest\Query\Clauses` are built from the nodes of `payload()`: `FilterGroupClause::of()`, `RelationGroupClause::of()`, `SortClause::of()`, `RelationSortClause::of()`, `AggregateSortClause::of()`, `TrashedClause::of()`, `ScopeClause::of()`, `InstructionClause::of()` and `AggregateClause::of()`. `fromPayload()`, `fromSort()`, `fromAggregate()`, `RelevanceClause::requestedBy()` and `RelevanceClause::fromSort()` are removed.
- An edit of the `search` input after validation, in an event such as `searching()` or `operating()`, does not change the search, on the database or on a search engine: neither its rows, its page and its page size, nor the rows that an action runs on, nor the fields, gates, aggregates and includes of the response. Read the search with `$request->payload()`.
- `SearchRequest::searchContext()` returns a `Lomkit\Rest\Search\SearchContext`, built once from `payload()`, for checks that read several parts of a search. Its `filters` list each filter as a `Lomkit\Rest\Search\FlatFilter` with its `path`, `operator`, `value` and `exists`: the filters of nested groups come with the others, a filter of a relation group takes the relation as a prefix (`posts.status`), and `exists` is `false` under a relation group whose `exists` is `false`. `filtersOn($path)` and `sortsOn($field)` tell whether the search filters or sorts on a name, a relation path such as `author.last_name` or the key of an aggregate included, `scopes()` and `includes()` list the names of its scopes and includes, and `text` and `trashed` hold those parts of the search. Like `payload()`, it throws a `LogicException` before the request passed validation. The resource's `searching()` event receives it, see [Events](#events): throw a `ValidationException` there to refuse a search; the response is a 422, and the search does not run.

  ```php
  use Illuminate\Validation\ValidationException;
  use Lomkit\Rest\Search\SearchContext;

  public function searching(SearchContext $search): void
  {
      if ($search->filtersOn('starts_at') && ! $search->filtersOn('ends_at')) {
          throw ValidationException::withMessages(['search.filters' => 'A starts_at filter needs an ends_at filter.']);
      }
  }
  ```

- A `SearchRequest` subclass whose `failedValidation()` does not throw gets the `LogicException` of `payload()` when the search runs: "The search was read before the request passed validation."
- An include that a dotted include goes through, such as `posts` next to `posts.author`, keeps its filters, sorts, scopes, instructions, limit, includes, aggregates and the `perimeterQuery()` of its resource whatever their order in `includes`. Before, it lost them when it was listed first.
- Each relation of a dotted include applies the `perimeterQuery()` of its resource, also when it is not listed as an include of its own: `posts` in `posts.author` loads only the posts that the `perimeterQuery()` of their resource returns, and `author` in `author.tags` renders `null` for an author that the `perimeterQuery()` of its resource leaves out. The rows below a left-out row are not rendered, and the limit of the relation counts the rows that `perimeterQuery()` keeps. Before, these levels ignored `perimeterQuery()` and rendered every row.
- When `includes` lists one relation twice without an alias, directly or through dotted paths, the response renders the last entry: its `selects`, `gates`, `aggregates` and `includes`, as its rows already did. Before, the response rendered the first entry over the rows of the last.
- `OperationNode::search()` returns the search of the action, empty when it is not sent. `Lomkit\Rest\Query\EngineSearch` takes the search as a third constructor argument and reads it: `query()` takes no argument, `paginate()` takes none and returns a `Lomkit\Rest\Search\Plan\PlannedPage`, which holds the `plan` of the page and the paginator of its `rows`, and `rows(array $keys)` loads the rows of a page.

### Text search

A resource declares its text search with `text()`. With the `Contains` and `FullText` strategies, `searchable()` marks the fields to search, and the text becomes one more condition of the SQL search: filters, scopes, instructions, includes, aggregates, sorts, pagination, `perimeterQuery()` and actions work with it as they do without it. `ScoutEngine` runs the text on a search engine instead, see [Search engines](#search-engines).

```php
use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Text\Contains;
use Lomkit\Rest\Text\FullText;
use Lomkit\Rest\Text\TextSearch;

public function fields(): array
{
    return [
        ID::make('id'),
        Text::make('name')->searchable(),
        Text::make('description')->searchable(),
        Integer::make('price'),
    ];
}

public function text(): ?TextSearch
{
    return FullText::make()
        ->index('name', 'description')
        ->otherwise(Contains::make());
}
```

Declaration:

- `Resource::text(): ?TextSearch` returns `null` by default. A resource without text search refuses `search.text` with a 422, "The search.text field is prohibited.". A model that uses `Laravel\Scout\Searchable` no longer gives its resource a text search: declare `text()` on the resource. If one of your resources already has a method named `text()`, rename it.
- `searchable()` marks a field that the `Contains` and `FullText` strategies read. Only `Text` fields, and app types that extend `Text`, can be searchable: `searchable()` throws `Lomkit\Rest\Exceptions\InvalidFieldException` on another type, and on a `virtual()` or `writeOnly()` field, whatever the order of the calls. `Lomkit\Rest\Fields\FieldSet::searchable()` returns the searchable fields.
- A resource whose `text()` returns `Contains` or `FullText` but that has no searchable field throws `Lomkit\Rest\Exceptions\InvalidTextSearchException` when it is searched.
- A text search implements `Lomkit\Rest\Text\TextSearch`, which only declares `capabilities()`. The strategies that add a condition to the SQL query implement `Lomkit\Rest\Text\QueryTextSearch`, and the strategies that run on a search engine implement `Lomkit\Rest\Text\EngineTextSearch`. A strategy that implements neither throws `InvalidTextSearchException` when the resource is searched, and so does an `EngineTextSearch` whose `capabilities()` return no engine grammar in `TextCapabilities::$engine`: the request is validated against that grammar.
- The strategies are in the `Lomkit\Rest\Text` namespace:
  - `Contains::make()` runs on every database. Each word of the text must appear in one of the searchable fields, as a part of its value. The words match in any order, and the words after the tenth are ignored. It runs `ILIKE` on PostgreSQL and `LIKE` on the other databases, and the `%`, `_` and `\` characters of the text match as they are typed. It supports the `words` and `phrase` modes, and does not order by relevance.
  - `FullText::make()` runs the full-text search of MySQL, MariaDB and PostgreSQL. It supports the four modes and orders by relevance.
    - `index('name', 'description')` declares a group of columns. Call it once per FULLTEXT index: a row matches when one of the groups matches. Without `index()`, the searchable fields form one group. A column that is not a searchable field throws `InvalidTextSearchException` when the resource is searched.
    - `language('english')` names the PostgreSQL text search configuration, `english` by default: letters, digits and `_` only. MySQL and MariaDB ignore it.
    - `otherwise(Contains::make())` runs another strategy on the other databases, such as SQLite and SQL Server. Without it, searching the resource on these databases throws `InvalidTextSearchException`, which names the resource and the database driver.
    - On MySQL and MariaDB, each group needs a FULLTEXT index on exactly its columns. A group without one throws `InvalidTextSearchException`, which names the table and the groups. Create one index per group in a migration: `$table->fullText(['name', 'description'])`. On PostgreSQL, the search runs without index, and the same call, with `->language(...)` when the configuration is not `english`, creates the index that the search uses.
    - `otherwise()` takes a strategy that adds a condition to the SQL query: a `ScoutEngine` there is a `TypeError`.
  - `ScoutEngine::make()` runs the text, the filters, the sorts and the pagination on the search engine of a Scout model, then loads the page in SQL, see [Search engines](#search-engines).

Request:

- `search.text` is an object: `{"value": "desk lamp", "mode": "phrase"}`. `value` is a string of at most 255 characters. A blank, `null` or missing value, or a value without a letter or a digit, runs the search without text and returns the normal list. A text made only of words that FullText ignores, such as stopwords, returns no row: see the database differences below.
- `mode`, `words` by default, is a case of `Lomkit\Rest\Text\TextMode`. A mode that the strategy does not support gives a 422 under `search.text.mode`.
  - `words`: the words of the text, in any order. With Contains and on PostgreSQL, a row must hold every word. On MySQL and MariaDB, a row matches when it holds one of the words, and without `sorts` the rows that hold more of them come first.
  - `phrase`: the words of the text, next to each other and in this order.
  - `prefix`: each word starts a word of the row, for a search box that shows results while the user types: `lam` finds `lamp`.
  - `websearch`: words, `"quoted phrases"`, `-excluded` words, and `or` between two alternatives.
- FullText keeps the letters and digits of the text. The other characters, such as `+`, `*`, `:`, `&`, `|` and `!`, separate words and have no meaning, except the quotes, `-` and `or` of the `websearch` mode. On MySQL and MariaDB, an `_` between two letters or digits stays in the word, as in their FULLTEXT index: `snake_case` is one word there, and two words on PostgreSQL.
- With `Contains` and `FullText`, the full search grammar works with text: filters with `or`, `nested`, relation paths and every operator, scopes, instructions, includes, aggregates, sorts, `page` and `limit`. `total` counts the matching rows. The text narrows the searched resource only: it does not narrow includes, aggregates or relation filters, and `text` in an include gives a 422. `viewAny` is checked before the search runs, and `perimeterQuery()` applies as in any search.
- With a strategy that orders by relevance, a search with text and without `sorts` returns the most relevant rows first, then follows `defaultOrderBy()`. The sort field `@relevance` places the relevance among the other sorts, `desc` when `direction` is omitted: `"sorts": [{"field": "featured", "direction": "desc"}, {"field": "@relevance"}]`. `@relevance` gives a 422 under `search.sorts.<i>.field` without text, in includes, and with a strategy that does not order by relevance, such as Contains or the `otherwise()` strategy of FullText.
- `search.trashed` replaces `search.text.trashed`, with or without text: `with` adds the soft-deleted rows, and `only` returns them alone. It gives a 422 on a model without `SoftDeletes` and in includes. `search.text.trashed` gives a 422.
- An action of a resource that declares `text()` accepts `search.text` and acts on the matching rows only. A blank text acts on every row that the rest of the search resolves. `search.trashed` applies to actions too.

Database differences:

- Contains: `ILIKE` ignores case on PostgreSQL. On MySQL and MariaDB, `LIKE` follows the collation of the column: the default `_ci` collations ignore case. On SQLite, `LIKE` ignores the case of ASCII letters only.
- FullText on MySQL and MariaDB:
  - Words are not stemmed: `lamps` does not find `lamp`.
  - Words shorter than `innodb_ft_min_token_size` (3 by default) and the InnoDB stopwords, such as `the` or `with`, are not indexed. The `words` mode ignores them and the `phrase` mode skips them, but a text made of such words only returns no row.
  - In the `prefix` mode, each word of the text must start an indexed word of the row. Short beginnings work: `l` and `la` find `lamp`. A stopword or a short word of the text only finds the longer indexed words that start with it: `the lam` finds `Theme lamp` but not `The lamp`, whose `the` is not indexed.
  - In the `websearch` mode each word is required, so a stopword or a short word finds no row and the search returns nothing. To search such words, lower `innodb_ft_min_token_size` or change the stopword list, then rebuild the indexes.
  - A `websearch` text made of excluded words only returns no row.
  - InnoDB only finds committed rows: in tests that run each test in a transaction, such as tests that use `RefreshDatabase`, a FullText search finds no row that the test wrote.
- FullText on PostgreSQL:
  - `language()` sets how words are read. With `english`, words are stemmed (`lamps` finds `lamp`) and English stopwords are ignored. A text made of stopwords only returns no row. With `simple`, words are only lowercased. Give the index the same configuration, `$table->fullText([...])->language('simple')`, otherwise PostgreSQL does not use it.
  - A row whose value is `null` in one column of a group does not match through that group. Declare the columns of a group `NOT NULL`, or give a nullable column its own `index()` group.
  - A `websearch` text made of excluded words only returns the rows without these words.

Query builder:

- `Builder::applySorts()` is removed. `@relevance` is a `RelevanceSort` in `payload()`.

Scramble:

- The search body documents `text` only on the resources with a text search: `value`, a string or `null` of at most the `max_length` of the strategy, and the `mode`s of the strategy. The `fields` key of `text`, which the package never read, is removed.
- `trashed` is documented on the resources whose model uses `SoftDeletes`, and the sort `field` lists `@relevance` when the strategy orders by relevance.

### Search engines

`Lomkit\Rest\Text\ScoutEngine` runs the text search of a resource on the search engine of its Laravel Scout model. The engine matches the text, applies the filters and the sorts, pages the results and counts them, then the package loads the rows of the page in one SQL query. It replaces the Scout text search that 2.x ran on every resource whose model used `Laravel\Scout\Searchable`.

```php
use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Fields\Number;
use Lomkit\Rest\Text\Engine\EngineQuery;
use Lomkit\Rest\Text\ScoutEngine;
use Lomkit\Rest\Text\TextSearch;

public function text(): ?TextSearch
{
    return ScoutEngine::make()
        ->filterable([Integer::make('category_id'), Number::make('price'), 'status'])
        ->sortable(['price'])
        ->perimeter(
            fn (EngineQuery $query) => $query->where('team_id', '=', $this->request()->user()->team_id),
            [Integer::make('team_id')],
        );
}
```

Declaration:

- The model uses `Laravel\Scout\Searchable`, and its engine, the result of `searchableUsing()`, is a Scout engine or a class that extends one: `collection`, `database`, `meilisearch`, `typesense`, `algolia`, `turbopuffer` or `null`. Another engine is refused, because the package cannot know which filters and sorts it runs.
- `filterable()` lists the index attributes that clients may filter on: fields, such as `Integer::make('category_id')`, or the names of fields of the resource, such as `'status'`, which take the type and the operators of that field. A name that points to a field declared `selectable(false)` needs that field to be declared `filterable()`; the attributes of `perimeter()` take such a field as it is. An attribute is an `ID`, `Integer`, `Number`, `Boolean`, `Text` or `Enum` field without `filterUsing()` or `sortUsing()`. `Date`, `DateTime`, `Json` and the app types that extend a field type are refused: index a date as a timestamp and declare it as an `Integer`. A name starts with a letter or `_` and holds only letters, digits and `_`, and `__soft_deleted` is reserved. An `Enum` field declared here reads its enum class from the casts of the model.
- `sortable()` names the `filterable()` attributes that clients may sort on.
- The index perimeter is required: declare `perimeter()` or `withoutPerimeter()`, and not both. The closure of `perimeter()` receives the `EngineQuery` of each search, and reads the request with `$this->request()`. Its conditions narrow every search of the resource, its total and its pages included, and clients cannot widen them. Its second argument lists the attributes that only the perimeter uses: clients cannot filter or sort on them.
- The perimeter decides which rows a text search returns: the rows of a page are loaded without `perimeterQuery()` (see Pages and totals). Declare in `perimeter()` every condition that keeps rows from some users, on attributes that the index holds and filters on, and keep these attributes in sync with the database: set `scout.after_commit` to `true`, and leave `scout.queue` off for a model whose perimeter keeps rows from some users, so that a change reaches the index before the next search. Until the index holds a change, a text search returns the row as the index matches it, with its current values.
- `searchable()` does not apply to `ScoutEngine`, which needs no searchable field.
- The declaration is checked when the resource is first searched in a request. A missing perimeter, a model without `Searchable`, another engine, a refused type, a callback, a refused name, an attribute declared twice or a sort on an attribute that is not filterable throws `Lomkit\Rest\Exceptions\InvalidTextSearchException`, which names the resource.

What the text matches:

- With `ScoutEngine`, `search.text` matches the attributes your index searches (with the `database` and `collection` engines, every key of `toSearchableArray()`); fields' `->searchable()` does not restrict them. Index only attributes that every user who can list the resource may read.
- Totals and pages follow the index perimeter: use `withoutPerimeter()` only when every such user may see every indexed row.
- The engine receives the words of the text: its letters and digits, separated by single spaces. The other characters separate words, so the query syntax of the engine, such as quotes or `-`, has no effect. `search.text.mode` accepts `words` only.
- A blank text, or a text without a letter or a digit, runs the normal SQL search, with its whole grammar.

Request:

- `search.filters` take the `filterable()` attributes, combined with `and`: the `or` type, `nested`, relation paths and relation groups give a 422. `=`, `!=`, `in` and `not in` work on every attribute, and `>`, `>=`, `<` and `<=` on the `Integer` and `Number` attributes, among the operators of the field. `in` and `not in` need a list of at least one value, and `null` gives a 422. Each value is validated by the rules of its field, then converted to the type of the attribute: a number that is not finite gives a 422.
- `search.sorts` take the `sortable()` attributes. `@relevance` keeps the order of the engine: it is accepted alone, without `direction` or with `desc`. `asc` gives a 422 under `search.sorts.0.direction`, and `@relevance` next to another sort gives a 422.
- `search.scopes` gives a 422. `search.instructions` takes the instructions that implement `AppliesToEngine`, see the instructions below.
- `search.trashed` needs a model that uses `SoftDeletes` and `scout.soft_delete` set to `true`, so that the index keeps the trashed models. Otherwise it gives a 422 next to a text. Without text, it follows the rules of the SQL search.
- `includes`, `aggregates`, `selects` and `gates` are validated and applied as in a SQL search, on the rows of the page. Each include and aggregate applies the `perimeterQuery()` of its own resource, at every level: the include of a row that the index returned can be `null` or leave rows out. The `update`, `delete`, `restore` and `forceDelete` gates of a page row answer `false` when `perimeterQuery()` leaves the row out, see [Responses](#responses).

Pages and totals:

- The engine pages the results with `search.limit` and `search.page`. `total` and `last_page` are the engine's, and the rows keep the order of the engine.
- The package loads the rows of the page in one SQL query, by their scout key (`getScoutKeyName()`), with the global scopes of the model and its soft deletes, as `search.trashed` sets them, and without `perimeterQuery()`: the index and its perimeter decide the rows, so a page holds `limit` rows and `total` counts the rows that the pages hold. A row that the index returns and that the database no longer holds, or that a global scope leaves out, is left out of `data`, and `total` still counts it. Before, `perimeterQuery()` applied to these rows too: a row that it left out was missing from `data` and counted in `total`. Move the conditions of `perimeterQuery()` that text searches need into `perimeter()`.
- Scout indexes a model when it is saved, whatever the global scopes of the model. On the `meilisearch`, `typesense`, `algolia` and `turbopuffer` engines, a row that a global scope leaves out can therefore be in the index: it is left out of `data`, and `total` still counts it. Keep these rows out of the index with `shouldBeSearchable()`, or add the condition of the scope to the perimeter.
- The keys follow `getScoutKeyType()`: `int` or `integer` gives integer keys, and any other type string keys. Scout returns the key type of the model by default, so a model whose scout key is not its primary key, such as a `code` column, declares `getScoutKeyType()`. A key that does not match the type throws `Lomkit\Rest\Exceptions\InvalidResourceException`.
- The package builds Scout's `Laravel\Scout\Builder` itself: an override of the model's `search()` method is not used, and the overrides of `getScoutModelsByIds()` and `queryScoutModelsByIds()` do not apply to the rows of the page. `Resource::paginate()` serves the SQL searches only.

Engines:

- `collection` and `database`: every operator and sort, exact totals. On PostgreSQL, Scout's `database` engine compares every key of `toSearchableArray()` with `ilike`, so return text columns only there. Scout's `database` engine selects only the `getScoutKeyName()` column of the rows that match, without the default eager loads of the model, then the package loads the rows of the page by those keys, as above: `getScoutKey()` returns the value of that column, as Scout's default does.
- `meilisearch`: exact totals, up to the `pagination.maxTotalHits` setting of the index (1000 by default). The index declares the `filterable()` and perimeter attributes in `filterableAttributes` and the `sortable()` attributes in `sortableAttributes`, for example through `scout.meilisearch.index-settings` and `php artisan scout:sync-index-settings`, which adds `__soft_deleted` when `scout.soft_delete` is `true`.
- `typesense`: exact totals. The model needs `query_by` in `scout.typesense.model-settings.<model class>.search-parameters`, or in `typesenseSearchParameters()`, otherwise the declaration throws. A page holds at most 250 rows: a larger `search.limit` gives a 422, so keep the `limits()` of the resource at 250 or below. A search takes at most 3 sorts.
- `algolia`: exact totals, no sorts: Scout's Algolia engine ignores them, so `sortable()` throws, and `@relevance` alone is accepted.
- `turbopuffer`: no sorts, because Turbopuffer refuses sorts next to a text: `sortable()` throws, and `@relevance` alone is accepted. A page must end within the first 10,000 results, otherwise `search.page` gives a 422. The total that Scout reports counts the documents that match the filters, not the text: `total` and `last_page` are given as Scout reports them, and can exceed the matching rows.
- `null`: an empty page and a total of 0, without SQL query.
- Meilisearch and Typesense compare filter strings without regard to case: prefer `Integer`, `ID`, `Boolean` or `Enum` perimeter attributes.
- Every search engine receives filter values as literals. A string value that holds a backtick or a colon followed by `(` or `[ (`, ends with `*`, is wrapped in double quotes, or is a number written with `+` gives a 422 as a filter value, and an `InvalidEngineQueryException` in the perimeter.

Engine queries:

- The perimeter and the instructions receive a `Lomkit\Rest\Text\Engine\EngineQuery`, not Scout's builder:
  - `where($attribute, $operator, $value)` takes `=`, `!=`, `<`, `>`, `<=` or `>=`, as a string or a `FilterOperator`. `whereIn($attribute, $values)` and `whereNotIn($attribute, $values)` take a list.
  - The attribute must be declared in `filterable()` or in the perimeter, with this operator. The value is converted to the type of the attribute, a backed enum to its value. Another attribute or operator, `null`, an array, an object or a number that is not finite throws `Lomkit\Rest\Exceptions\InvalidEngineQueryException`, a `\LogicException`.
  - The conditions add up and never replace each other: two `whereIn()` on one attribute keep the values that both lists hold, and two `whereNotIn()` exclude the values of both lists. An empty `whereIn()` list matches nothing, and the search then returns an empty page without calling the engine. An empty `whereNotIn()` list changes nothing.
  - `orderBy($attribute, $direction)` sorts on a `sortable()` attribute.
  - `options([...])` adds options for the engine to the options already set. An option set again with another value throws, and so do the options that the package sets itself, such as `filter`, `sort`, `page` and `hitsPerPage` on Meilisearch.
  - It has no `query()` or search callback.

Instructions:

- `instructions()` is the only list of instructions. An instruction applies to a text search on a search engine when it implements `Lomkit\Rest\Instructions\AppliesToEngine`, whose `handleEngine(InstructionFields $fields, EngineQuery $query): void` receives the input values, as `handle()` does. Another instruction gives a 422 there.
- An instruction that implements `AppliesToEngine` and does not override `handle()` only applies to these searches: it gives a 422 in a SQL search, such as a search without text or with a blank text, an include, an action without text, or the search of a `Contains` or `FullText` resource.

  ```php
  use Illuminate\Database\Eloquent\Builder;
  use Lomkit\Rest\Fields\Number;
  use Lomkit\Rest\Instructions\AppliesToEngine;
  use Lomkit\Rest\Instructions\Instruction;
  use Lomkit\Rest\Instructions\InstructionFields;
  use Lomkit\Rest\Text\Engine\EngineQuery;

  class PricedUpTo extends Instruction implements AppliesToEngine
  {
      public function fields(): array
      {
          return [Number::make('max')->rules('required')];
      }

      public function handle(InstructionFields $fields, Builder $query): void
      {
          $query->where('price', '<=', $fields->max);
      }

      public function handleEngine(InstructionFields $fields, EngineQuery $query): void
      {
          $query->where('price', '<=', $fields->max);
      }
  }
  ```

- In the details, each instruction adds `text`: whether it applies to a search with text on this resource. It is `false` on a resource without text search; on `Contains` and `FullText`, `true` unless the instruction only applies to search engines; on `ScoutEngine`, `true` for the instructions that implement `AppliesToEngine`.

Actions:

- An action whose search has a text on a search engine resolves its targets before it runs, is queued or starts a batch: it reads the keys from the engine in pages of `chunkCount`, then loads each chunk in SQL by key, with the global scopes and `search.trashed`, without `perimeterQuery()`, as the rows of a page. Before, `perimeterQuery()` applied there too, and the action skipped the rows that it left out. An action that changes indexed attributes still acts on each matching row once, and `impacted` is the number of models given to `handle()`.
- A search that matches more than `maxResources` resources gives a 422 under `search`, and the action does not run: "This action can be run on at most 1000 resources; narrow the search." The count is the engine's total, the rows that the action acts on. With `search.limit`, the action acts on the first `limit` results.
- Meilisearch and Algolia count the results up to a setting of the index: `pagination.maxTotalHits` on Meilisearch and `paginationLimitedTo` on Algolia. Without `search.limit`, the action reads this setting, so the key of the application must be allowed to read the settings of the index. Set it above the `maxResources` of the actions: when it is not above, or the action has no cap, and the search reaches it, the action gives a 422, "The search engine returns at most 1000 results for this search; narrow the search.". When the settings cannot be read, the action gives a 422, "The search engine does not report how many results this search can reach; give the search a limit.".
- On Turbopuffer, whose total does not count the text, the action reads the keys until a page is not full.
- When the results change while the action reads them, it gives a 422, "The search results changed while the action was resolving them; run the action again.". Set `scout.after_commit` to `true`, so that the index changes once the database transactions commit.
- The actions of `Contains` and `FullText` resources act on the rows of the SQL search, in chunks, capped by `maxResources` as well, see [Actions](#actions).

From the Scout text search of 2.x:

| 2.x | 3.0 |
| --- | --- |
| `use Searchable` on the model enables `search.text` | `text()` returns `ScoutEngine::make()` |
| `scoutFields()` | `ScoutEngine::make()->filterable([...])->sortable([...])` |
| `scoutInstructions()` | `instructions()`, with `AppliesToEngine` on each instruction |
| `handleScout(array $fields, Builder $query)` | `handleEngine(InstructionFields $fields, EngineQuery $query)` |
| `searchScoutQuery()` | `ScoutEngine::make()->perimeter(...)` |
| `isModelSearchable()` | removed |

- These methods and `isModelSearchable()` no longer exist on the package classes: an override marked `#[\Override]` is a fatal error when PHP loads the class, and a method without the attribute is no longer called. Delete them.
- `Lomkit\Rest\Query\ScoutBuilder` is removed.
- The rows of a search engine page are loaded by key from the query of the model, with its global scopes, `search.trashed` and the includes and aggregates of the search, without `perimeterQuery()`.
- The engine query is built from the nodes of `payload()`: `Lomkit\Rest\Text\Engine\EngineCriteria::of(FilterGroup $filters, array $sorts)` applies the `EngineFilterCondition` filters and the `EngineSort` sorts, and leaves `@relevance` to the order of the engine, and `EngineInstruction::of(InstructionNode $node)` passes the `InstructionFields` of an instruction to `handleEngine()`. `fromSearch()` is removed from both, and so is `InvalidInstructionException::undeclared()`.
- The details response no longer has the `scout_fields` and `scout_instructions` keys.

Scramble:

- On a `ScoutEngine` resource, `text` documents `value` and the `words` mode, and `trashed` says when it cannot be combined with a text. `filters` and `sorts` keep the schema of the SQL search, which applies without text.

### Validation engine

Each package request validates its payload with one Laravel validator, in one pass. Before validating, the request turns its payload into rules on explicit keys, and the validator checks those keys. Parsers read the `search` payload, the operate payload and the `mutate` payload into rules on explicit keys, such as `search.filters.0.field`, `fields.0.name` and `mutate.0.attributes.name`. No package payload is compiled from a rule tree any more.

Custom rules:

- `Lomkit\Rest\Rules\RestRule`, `Lomkit\Rest\Rules\MaxItems`, `Lomkit\Rest\Rules\ArrayWithKey`, the classes of `Lomkit\Rest\Rules\Mutate` and `Lomkit\Rest\Rules\Resource`, and `Lomkit\Rest\Validation\RuleCompiler` are removed. The check of the `relation` of includes and aggregates is now `Lomkit\Rest\Validation\Search\KnownRelation`. `WriteRules::under()`, `PivotRelation::pivotRules()` and `HasPivotFields::pivotRules()` are removed too, see [Pivot fields](#pivot-fields).
- The `rules()` of a request hold Laravel rules only. Use Laravel's `max` rule in place of `MaxItems`, such as `'tags' => ['array', 'max:20']`.
- `Lomkit\Rest\Rules\Concerns\ValidatesFields` and `Lomkit\Rest\Rules\Operate\OperateFields` are removed. `Lomkit\Rest\Rules\Input\InputFields` is removed. The parsers validate the fields of actions and of instructions. Their error keys do not change. A `validation.custom.<name>.<rule>` translation line no longer applies to an input field: key it by the full key, `validation.custom.fields.<name>.<rule>` for an action or `validation.custom.search.instructions.*.fields.<name>.<rule>` for an instruction. The `validation.attributes.<name>` line still names the field, unless the field declares `label()`.
- Laravel expands the wildcard rules of a request's `rules()` and of the resource rules, such as `mutate.*.attributes.name` or `metadata.tags.*`. It replaces `*` in the parameters of its dependent rules there, such as `required_if`, and in those of a rule registered with `Validator::extendDependent()`. `Validator::setImplicitAttributesFormatter()` applies to a key that Laravel expands only when no other name applies to it, see "Labels and messages" below. The package gives a key that it parses its name as written only when the key holds `_`, whitespace or an uppercase letter, so the formatter still applies to the other parsed keys that such a rule meets, such as `mutate.0.attributes.name` or `search.filters.0.field`. A data key that holds `*` under such a rule is validated as written. Before, it gave "The * key is not valid for the X field.".
- The errors of the keys that Laravel expands from a wildcard of the resource rules, such as `mutate.0.attributes.metadata.tags.1`, come after the other errors of the request. Before, they came with the attribute errors of their entry.
- The rules of action fields, instruction fields and scope parameters run in the request's validator. A rule that names another input of the same action, instruction or scope compares with that input: this covers the field parameters of Laravel's dependent rules (`required_if`, `gte`, `after`, `same`, …), `date_equals` and `in_array`, written as strings or arrays, in `Rule::date()`, `Rule::numeric()`, `Rule::string()` and `Rule::array()` objects, and inside `Rule::when()`.
- Other rule objects and closures on these inputs see the whole request: a `DataAwareRule` receives the whole payload, where each input value sits under `<attribute>.<name>` (`fields.min`), a `ValidatorAwareRule` receives the request's validator, a `Rule::when()` condition receives the whole payload, and the attribute passed to `validate()` or to a closure is the full key, such as `fields.min` or `search.instructions.0.fields.min`.
- `Lomkit\Rest\Rules\OneOf` is `Stringable`: it casts to an `in` rule that lists its names, such as `in:name,price`. A field rule that uses it shows as `in:...` in the details response.
- A rule of a field or of the resource rules can depend on the entry of `mutate` that it validates. `Lomkit\Rest\Mutations\Contextual::rule()` takes a closure that receives a `Lomkit\Rest\Mutations\MutationContext` and returns a rule, a pipe string or a list of rules. The context holds the `operation` of the entry (a `MutationOperation`), its `key` (the parsed key, `null` on `create` and for a key list) and its `depth` (0 at the root, one more per relation level):

  ```php
  use Illuminate\Validation\Rule;
  use Illuminate\Validation\Rules\Unique;
  use Lomkit\Rest\Mutations\Contextual;
  use Lomkit\Rest\Mutations\MutationContext;

  Text::make('title')->rules(
      Contextual::rule(fn (MutationContext $mutation): Unique => Rule::unique(Post::class, 'title')->ignore($mutation->key)),
  ),
  ```

- A rule object that implements `Lomkit\Rest\Mutations\MutationAwareRule` receives the context of each entry in `setMutation(MutationContext $mutation): static`. The package calls it on a clone of the rule for each entry, and uses the rule that it returns.
- `Contextual::rule()` and `MutationAwareRule` apply to the attributes and the pivot of a mutation, in field rules and in the resource rules. A `Contextual` rule that runs anywhere else, such as in the rules of an action field or in a request's `rules()`, throws a `LogicException`: "A contextual rule only applies to the attributes and the pivot of a mutation."
- In the field rules and the resource rules of a mutation, a `Rule::when()` condition receives the attributes of its entry, or the pivot values of its entry for a pivot field. Before, it received the whole payload. The inputs of actions, instructions and scopes keep the whole payload, as said above.
- In `mutate`, the `rules()`, `createRules()` and `updateRules()` of a resource are read once per request, not once per entry. The rule objects they return are shared by the entries, `create` and `update` entries alike. A rule object that keeps state per entry implements `MutationAwareRule`, or is built in `Contextual::rule()`.

Requests:

- `withValidator()` and `after()` on a package request run once, and their validator holds every error of the payload, including those of nested filters, includes, instructions and relations.
- `RestRequest` overrides five methods of `FormRequest` and declares their return types: `validationData(): array`, `validationRules(): array`, `createDefaultValidator(ValidationFactory $factory): Validator` (the `Illuminate\Contracts\Validation\Validator` contract), `shouldValidatePrecognitiveAttribute($attribute, $validateOnly): bool` and `shouldFailOnUnknownFields(): bool`. An override must declare the same type, and should call the parent method.
- `validationRules()` returns the parsed rules, with the rules of the request's `rules()` added, and `validationData()` returns the payload with each input value added under `<attribute>.<name>`, such as `fields.status`.
- `SearchRequest::rules()` returns rules on explicit keys, such as `search.filters.0.field`, instead of a rule tree. `OperateRequest::rules()` returns rules on explicit keys too: the search keys, `resources.0`, `resources.1`, …, `fields.<name>`, and `fields.0.name`, `fields.1.name`, … in place of `fields.*.name`. For an action with the `Search` target, `search` has a rule only when the request sends it. The errors of `fields.0.name`, `fields.1.name`, … now come before the errors of a key that an override adds after `...parent::rules()`. Before, they came after them. So the `message` of the 422 response can start with another error. `MutateRequest::rules()` returns rules on explicit keys too, such as `mutate.0.operation` and `mutate.0.relations.products.1.key`, instead of a rule tree, and a key that an override adds after `...parent::rules()` reports its errors after those of the package keys. On a key that the package validates in `mutate`, such as `mutate.0.attributes.name`, the rules that the override adds run after the package rules of that key, whether the override names the key or writes a wildcard rule such as `mutate.*.attributes.name`. Before, they ran first, except for a wildcard rule written after `...parent::rules()`. So when an added rule and a package rule of one key both fail, the first message of the key, and the `message` of the 422 response, can change. `relations` has a package rule only when the entry sends it: a rule that an override adds on `mutate.*.relations`, such as `required`, also runs on an entry without `relations`.
- An override of `rules()` must call the parent: without it, the search is not read, and on `OperateRequest` and `MutateRequest` the operate or mutate payload is not read and `payload()` throws. A rule that the override adds for a key that the package validates is added to the package rules of that key: `[...parent::rules(), 'search.limit' => ['max:10']]` keeps the package rules of `search.limit` and adds `max:10`. The package rules of a parsed key, such as a search key, `fields.0.name` or `mutate.0.operation`, apply even when the override replaces or leaves out its entry. A wildcard rule that the override adds, such as `resources.*`, `fields.*` or `mutate.*.attributes.name`, runs on each item, whether it is written before or after `...parent::rules()`. It does not run when the list that its first `*` stands for, such as `mutate`, is over its limit. A rule with more than one `*`, such as `mutate.*.relations.products.*.attributes.name`, still runs on the entries of a nested list that is over its limit, such as `mutate.0.relations.products`. For a key without `*`, write `...parent::rules()` first: the parent's entry replaces an entry written before it, and `+` keeps the parent's entry, so the rule of the override is lost there.
- A wildcard rule that the override adds on the entries of an instruction's `fields`, such as `search.instructions.*.fields.*`, also meets the named input values, such as `search.instructions.0.fields.min_views`. To check the entries only, write the rule on `search.instructions.*.fields.*.value` or `.name`, or give the rule to the field that the instruction declares in `fields()`.
- `SearchRequest::payload()` returns the validated search as a `Lomkit\Rest\Search\SearchNode`. Its parts are typed nodes: `FilterGroup`, `FilterCondition`, `RelationFilterGroup` and `MorphFilterGroup` (each a `FilterNode`), a `SortNode` per sort, `AggregateNode`, `IncludeNode`, `MorphIncludeNode`, `ScopeNode` and `InstructionNode`, in `Lomkit\Rest\Search`. A `FilterCondition` holds the `name` of its field as sent and its `path`, a `Lomkit\Rest\Fields\FieldPath` (`relations`, `field`, `resource`, `pivotTable`). Under a search engine, a filter is a `Lomkit\Rest\Text\Engine\EngineFilterCondition`, which holds its `IndexAttribute`. `SortNode` is an interface with `direction()`, `asc` by default and `desc` for `@relevance`: `FieldSort` holds the `Field`, `RelevanceSort` stands for `@relevance`, and `Lomkit\Rest\Text\Engine\EngineSort` holds the attribute of a search engine. An `IncludeNode` holds `relations`, the `Relation` of each segment of its path, and the related `resource`. An `AggregateNode` holds the related `resource`, its `field` (a `Field`, or `null`) and its `key`, a `Lomkit\Rest\Search\AggregateKey` whose `value` is the alias, or the default name. A `RelationFilterGroup` holds its `Relation`, `exists` and the `FilterGroup` of its `nested`. On a `MorphTo`, a `MorphIncludeNode` holds an `IncludeNode` for each type in `branches`, and a `MorphFilterGroup` holds a `FilterGroup` for each type in `types`. `payload()` throws a `LogicException` before the request passed validation: "The search was read before the request passed validation."
- `OperateRequest::payload()` returns the validated operation as a `Lomkit\Rest\Actions\OperationNode`. The node has `action`, `fields`, a `Lomkit\Rest\Fields\InputValues`, and `target`. The target is one of these: a `Lomkit\Rest\Search\SearchNode` for the `Search` target, which is empty when `search` is left out; the `Lomkit\Rest\Validation\KeyList` of the `Resources` target; `null` for the `None` target. `keys()` returns the `KeyList`. `payload()` throws a `LogicException` before the request passed validation: "The operation was read before the request passed validation."
- `MutateRequest::payload()` returns the validated mutation as a list of `Lomkit\Rest\Mutations\MutationNode`, one per entry of `mutate`. A node has `operation` (a `Lomkit\Rest\Fields\MutationOperation`), `key`, `attributes`, `pivot`, `withoutDetaching`, `relations`, which holds the nodes of each relation that the entry sends, and `depth`. `context()` returns its `MutationContext`. The `mutating()` and `mutated()` hooks receive these nodes, see [Events](#events). `payload()` throws a `LogicException` before the request passed validation: "The mutation was read before the request passed validation."
- Every package request records its validation pass in `passedValidation()`. An override of `passedValidation()` must call the parent.
- To adjust the validator, use `withValidator()`, or override `createDefaultValidator()` and call the parent. A request that defines `validator()` builds the validator itself: it must use `validationData()` and `validationRules()`. The `label()` and `messages()` of fields do not apply there, and Laravel names its keys, such as `fields.status` for an input field.
- Every key that the package parses in `mutate` is named as written in messages, at every level, as Laravel names wildcard keys, unless the request's `attributes()`, the field's `label()` or a `validation.attributes` translation line names it. Examples: `mutate.0.without_detaching`, `mutate.0.attributes.published_on`, `mutate.0.relations.latestLoan.operation`, `mutate.0.relations.lineItems.0.operation`, a relation that the entry does not send, such as `mutate.0.relations.featuredProducts`, and an explicit key of the resource rules, such as `mutate.0.attributes.metadata.source_url`. A key that Laravel expands from a wildcard rule of the request or of the resource rules, such as `mutate.0.attributes.metadata.tags.1`, is named as written too. The package gives a parsed key its name as written only where Laravel's formatting would change the key: when it holds `_`, whitespace or an uppercase letter. Laravel's formatting leaves the other parsed keys as written. `Validator::setImplicitAttributesFormatter()` applies to a key that Laravel expands only when no other name applies to it. So it does not apply to a parsed key that a wildcard rule meets when the key holds `_`, whitespace or an uppercase letter, such as `mutate.0.attributes.published_on` under `mutate.*.attributes.published_on`, or when its field declares `label()`. It still applies to the other parsed keys, such as `mutate.0.attributes.name` under `mutate.*.attributes.name` or `mutate.0.relations.products.0.operation` under `mutate.*.relations.products.*.operation`.
- The input values added by `validationData()` also appear in `validated()` and `safe()`, under `fields.<name>` for an action. `handle()` receives these values, cast, in its `ActionFields`.
- When the payload has no `fields`, `all()` and `validated()` no longer hold `fields: []`. A rule that a request adds on `fields` itself now meets an absent key. So `min:1` and `filled` no longer fail there, and `present` now fails. Before, the request merged `fields: []` before validation.
- The request's `messages()` and `attributes()` now apply to the fields of actions and instructions, under `fields.<name>` and `search.instructions.<i>.fields.<name>`. Without them, the field's `label()` names such a field, then the `validation.attributes.<name>` translation, or else its name.
- `FormRequest::failOnUnknownFields()` and the `#[FailOnUnknownFields]` attribute do not apply to package requests: `shouldFailOnUnknownFields()` returns `false`.
- With Precognition enabled, a name in `Precognition-Validate-Only` also covers the keys under it: `search` validates `search.limit` and `search.filters.0.field`. Before, a payload with invalid nested values could return `204`.

Labels and messages:

- Fields take `label(string $label)` and `messages(array $messages)`, read through `getLabel(): ?string` and `getMessages(): array`. The label names the field's key in validation messages. A blank label counts as no label. The messages replace Laravel's text for the rules of that key. They are keyed by rule name, such as `required`, `max` or `enum`. A later `label()` replaces the label, and `messages()` merges with the messages already declared.
- They apply to the attributes of `mutate` at every level, to the pivot fields of a many-to-many entry, to the fields of actions and instructions, and to the parameters of scopes. Error keys do not change. On `create`, this field gives "The email address field is required." and "This email address is already registered." under `mutate.0.attributes.email`:

  ```php
  Text::make('email')
      ->label(__('email address'))
      ->creationRules('required', 'unique:users,email')
      ->messages(['unique' => __('This :attribute is already registered.')]),
  ```

- The package does not translate a label or a message: pass a translated string, as above. The declarations are read while the request is handled, so `__()` follows the locale of the request.
- A key is named in messages by, in this order:
  - the request's `attributes()`;
  - the field's `label()`;
  - a `validation.attributes` line of the full key or of a pattern;
  - for a field of an action or an instruction, the `validation.attributes.<name>` line, or else its name in snake case with spaces;
  - for a key that the package parses: the key as written, see "Requests" above.

  Laravel names the other keys, such as a key that only the request's `rules()` add.
- The labels of fields are added under the request's `attributes()` as patterns, such as `mutate.*.attributes.email`, or as full keys where two declarations meet on one pattern. A pattern of the request's `attributes()`, such as `mutate.*.attributes.email` or `search.instructions.*.fields.min_views`, names each key that it matches before the field's `label()`, in both cases.
- A rule's text comes from, in this order:
  - the request's `messages()` for the key and the rule, such as `mutate.0.attributes.email.unique` or `mutate.*.attributes.email.unique`;
  - the field's `messages()`;
  - a request message keyed by the rule alone, such as `unique`, or by the key alone;
  - a `validation.custom` line;
  - Laravel's line.

  For a rule object, such as the rule of an `Enum` field, a `ValidationRule` class or a closure, a request message keyed by the key alone, by the class name or by `<key>.<class name>` comes before the field's message, and no `validation.custom` line applies. The field's message is keyed by the snake class name, such as `enum`.
- Every key that the package parses in `mutate` is named as written at every level when no other name applies, such as `mutate.0.without_detaching` and `mutate.0.relations.latestLoan.operation`. `Validator::setImplicitAttributesFormatter()` still applies to a parsed key that a wildcard rule meets when the key holds no `_`, no whitespace and no uppercase letter and no other name covers it, such as `mutate.0.attributes.name`, see "Requests" above.

Payloads:

- A rule of a scope parameter that names another field of the same scope, such as `gte:min`, compares with the parameter at that field's position, `search.scopes.<i>.parameters.<p>`. Before, it read the key of that name at the root of the payload.
- An input field with a numeric name, such as `Integer::make('2')`, throws `InvalidFieldException` when the fields are built, because its value is validated under `<attribute>.<name>`, next to the entry positions.
- The `fields` of an action or an instruction must be a list of `{name, value}` entries. An object keyed by input name gives a 422: `The fields field must be a list.`
- In a mutate entry, a key that holds `*` or ends with `\`, such as `"mutate": [{"operation": "create", "*": 1}]`, gives a 422 on the entry: `The * key is not valid for the mutate.0 field.`
- In a search, a key that holds `*` or ends with `\` inside an object, such as a filter, gives a 422 on that object: `The * key is not valid for the search.filters.0 field.`
- The lists of a search must be JSON lists: `filters`, each `nested` group, `sorts`, `selects`, `scopes`, `aggregates`, `includes`, `instructions`, `gates`, and the `filters` of includes and aggregates. An object gives a 422 on the list, `The search.filters field must be a list.`, and its entries are not validated. Before, an object was read entry by entry, and a `*` entry key gave `The * key is not valid for the search.filters field.`
- A list entry that is not an object, such as `"includes": ["category"]` or `"mutate": ["create"]`, gives a 422 on that entry: `The search.includes.0 field must be an array.` A to-one relation given a list or `null` in `mutate` also gives a 422. Some of these payloads gave a 500 before.
- Under a to-many relation in `mutate`, the errors are listed entry by entry.
- `sync` and `toggle` are accepted on many-to-many relations only: `BelongsToMany`, `MorphToMany` and `MorphedByMany`. On another relation they give a 422 under the `operation` key of the entry, such as `The selected mutate.0.relations.author.operation is invalid.` Send `attach` there instead. A relation lists the operations it accepts in `mutationOperations()`.

Search payload:

- A key that the package does not know inside a search object gives a 422 on that key, named as written: "The search.filters.0.operater field is prohibited." This covers the search, includes, filters (nested filters and the filters of includes and aggregates included), sorts, selects, scopes, aggregates, instructions, the entries of the `fields` of an instruction, and `text`. Before, the key was ignored, and a misspelled `operator` fell back to `=`. The other keys of the object are still validated.
- An include no longer accepts `page`, which no include query applied: "The search.includes.0.page field is prohibited." Before, it was validated and then ignored. An include `types` gives the same 422.
- A rule that a request adds in `rules()` for a key inside `search` that the package does not know no longer makes that key valid: the key keeps the 422 above. A rule added for a key that the package validates is added to the package rules of that key, as before. Such a rule now also runs when the key is absent: `'search.filters' => ['required']` or `'search.sorts.*.direction' => ['required']` gives a 422 when the search leaves that key out. Before, a rule on an optional key of the search, such as these two, was skipped whenever `search` was sent without that key.
- The search still runs from the `search` payload that the request receives, read as before.

Operate payload:

- Inside an entry of the `fields` of an action, a key that the package does not know gives a 422 on that key, named as written: "The fields.0.default field is prohibited." A key that holds `*` or ends with `\` gives a 422 on the entry: "The * key is not valid for the fields.0 field." Before, the key was ignored. The other keys of the entry are still validated.
- A rule that a request adds in `rules()` for a key that the package does not know inside an entry of `fields`, such as `'fields.*.default' => ['boolean']`, no longer makes that key valid: the key keeps the 422 above.
- In a subclass of `OperateRequest`, a rule on `fields.*` also meets the named input values, such as `fields.notify`, as a rule on `search.instructions.*.fields.*` does. To check the entries only, write the rule on `fields.*.value` or `fields.*.name`, or give the rule to the field that the action declares in `fields()`.
- An action with the `Resources` or `None` target now reads the `text()` declaration of its resource during validation, as any other action does. A `text()` declaration that throws `Lomkit\Rest\Exceptions\InvalidTextSearchException` now also fails these actions.

Mutate payload:

- Inside a mutate entry, a key that the package does not know gives a 422 on that key, named as written: "The mutate.0.atributes field is prohibited." Before, the key was ignored. The keys of an entry are `operation`, `attributes`, `key`, `relations` and `without_detaching`, plus `pivot` in an entry of a `BelongsToMany`, `MorphToMany` or `MorphedByMany` relation, and `type` in an entry of a `MorphTo` relation. `pivot` and `type` get the same 422 in the other entries. The other keys of the entry are still validated.
- A `MorphTo` entry sends `type`, the morph type of its target, as `@type` in a search: `{"operation": "attach", "type": "shelf", "key": 2}`. It is required when the relation has several targets; with one target it can be left out, and must be that type when sent. The key, the attributes, the nested relations, the `create` check and the attach or detach policy method (`attachShelf`) are those of that type. An unknown type gives `The selected mutate.0.relations.labelable.type is invalid.`, and a key that the table of that type does not hold `The selected mutate.0.relations.labelable.key is invalid.`. Before, `type` was accepted and the entry was written to the declared resource.
- `Relation::entryRule()` returns the `Lomkit\Rest\Validation\Mutate\EntryRule` of the entries of a relation: the keys that they take besides the keys of every entry, and the resource that each entry writes. A `MorphTo` returns a `MorphToEntryRule`, which reads `type`; the other relations return a `RelatedEntryRule`.
- An entry or a to-one relation given as a list gets this 422 on each of its items, then the errors of the keys it lacks: "The mutate.0.relations.category.0 field is prohibited." and "The mutate.0.relations.category.operation field is required.".
- A rule that a request adds in `rules()` for a key that the package does not know inside a mutate entry, such as `'mutate.*.flag' => ['boolean']`, does not make that key valid: the key keeps the 422 above.
- A name in `relations` that the resource does not declare gives "The suppliers key is not valid for the mutate.0.relations field." on `relations`. The message names its field as other messages do, such as `mutate.0.relations.latestLoan.relations` under a to-one relation.
- `mutate`, each to-many relation and each `key` list must be JSON lists. An object gives a 422 on the list, "The mutate field must be a list.", and its entries are not checked: a denied creation in it gets this 422, where it got a 403 before. Before, an object was read entry by entry, and `{"*": ...}` gave "The * key is not valid for the mutate field.".
- A `key` is typed like the `ID` field of its resource: "The mutate.0.key field must be an integer." (or a string, a UUID or a ULID, by key type; a resource without an `ID` field uses the model's key type). A `null` key gets the type message too. Before, a key of the wrong type gave "The selected mutate.0.key is invalid.", or a 500 on PostgreSQL.
- A key list is accepted only in an entry of a to-many relation. At the root and in a to-one relation, a list gets the type message. Before, it was validated, and then the request failed. Each item of a key list gets its type message under `key.0`, `key.1`, ….
- The keys are checked with one query per resource class and request, per 1,000 keys, through the `perimeterQuery()` of their resource and the global scopes of the model, at the root and under relations. This query loads the rows (`select *`), and the mutation writes these rows: each row that the payload names by key is read once, during validation. A key that this query does not return, a soft-deleted key included, gives "The selected mutate.0.key is invalid.". A key list with a missing key gives one error on its `key`, such as "The selected mutate.0.relations.products.0.key is invalid.". Two forms of one UUID or ULID key that differ only by case are checked apart, each in a query of its own, and each passes only when the database matches it as sent. A `Precognition-Validate-Only` request whose names leave out every `key` runs no key query.
- `MutateRequest` sets `$request->resource` in `rules()` and has `keysQuery(Resource $resource): Illuminate\Database\Eloquent\Builder`. The `perimeterQuery()` of each resource class whose keys the payload holds runs once per request, during validation, before `mutatingAll()`, also when a key is sent in two cases. It is not called when the mutation is written, and a mutation that names no key of a resource, such as one that only creates rows, does not call it. `MutateRequest::rows()` returns the `Lomkit\Rest\Mutations\RowSet` of the rows that validation loaded, once the request passed validation.
- On `create`, `key` is not read: it gets only "The mutate.0.key field is prohibited when mutate.0.operation is create.", and no key query runs for it. Before, a key list there also got the `values` limit error and counted toward `totals.entries`.
- A mutation is written from the entries of `payload()`. The `mutating()` and `mutated()` hooks of the resource receive the `MutationNode` of each entry, see [Events](#events). A `MorphTo` entry holds `type`: the type that it sent, or the one target's type. A change to the request input after validation, such as in `mutatingAll()`, does not change what is written.

Limits:

- Each list of a request accepts a limited number of entries, filters, includes and relation operations nest up to a set depth, and two totals bound one request. The values are in the new `limits` block of `config/rest.php`, in four groups, `sizes`, `depths`, `totals` and `lengths`. See [Configuration](#configuration) for the block, its defaults and the values it accepts.
- Each key of `sizes` applies to these lists, at every level of a search, including the search of an include and of an action:
  - `filters`: `search.filters`, each `nested` group, and the `filters` of includes and aggregates;
  - `sorts`, `selects`, `scopes`, `aggregates`, `includes` and `instructions`: the list of that name;
  - `fields`: the `fields` of an action and of each instruction;
  - `values`: the values of an `in`, `not in`, `between` or `not between` filter, and the `key` list of an entry of a to-many relation in `mutate`;
  - `mutate`: the entries of `mutate`;
  - `relation_entries`: the entries of each to-many relation in `mutate`;
  - `resources`: the keys of `destroy`, `restore` and `forceDestroy`. An action with the `Resources` target uses its `$maxResources` property instead.
- `depths` sets how deep filters, includes and relation operations nest: `filters`, `includes` and `mutations`.
- `totals` sets two totals:
  - `filters`: the filters of one search, at every depth and in its includes and aggregates;
  - `entries`: the entries of every list in `sizes` and of every `gates` list in one request, except `resources`.
- `lengths.patterns` sets the most characters of the value of a `like`, `not like`, `ilike` or `not ilike` filter, 255 by default, wherever a search takes filters. A longer value gets a 422 on the value: "The search.filters.0.value field must not be greater than 255 characters." Raise `lengths.patterns` if your clients send longer patterns.
- The `parameters` of a scope keep their limit: one value per field of the scope.
- A list over its limit gets a 422 on its own key, such as `search.filters`: "The search.filters field must not have more than 100 items." This message comes first on the key, and the package does not validate the entries of the list. A wildcard rule of the request with more than one `*` can still meet them, see "Requests" above.
- Includes nest up to `depths.includes` levels, 3 by default. A fourth level gives a 422: "The search.includes.0.includes.0.includes.0.includes field is prohibited." Each relation of a dotted include counts toward `depths.includes`: `team.tickets.tags` takes three levels. An include that goes deeper gives a 422 on its relation, "The search.includes.0.relation field goes deeper than the limit of 3 include levels.", and `includes` below a dotted include is prohibited sooner. Before, a dotted include counted as one level. Relations in `mutate` nest up to `depths.mutations` levels, 3 by default: "The mutate.0.relations.products.0.relations.category.relations.products.0.relations field is prohibited." An empty list passes at that depth.
- The two totals give a 422 on the list that goes over them: "The search.includes.0.filters field goes over the limit of 500 filters in one search." for `totals.filters`, and "The search.selects field goes over the limit of 1000 list entries in one request." for `totals.entries`. A later list that does not fit is refused without a second message.
- Filters nest up to `depths.filters` levels, 1 by default, which replaces `search.max_nesting_depth`, see [Configuration](#configuration). Each `nested` key counts as one level, so 1 allows a single group of filters. The `nested` of a relation group counts the same way. With 1, it holds filters and relation paths, and a group or a relation group inside it gives a 422 on its own `nested` key. A relation group without `nested`, or with an empty list, fits at every depth. A text search run by a search engine never nests filters. Each `nested` list takes the `sizes.filters` limit and counts in `totals.filters`. Nothing below a group refused at that depth is validated: the request only gets the error on its `nested` key.
- On a search engine, `search.sorts` accepts the smaller of `sizes.sorts` and the number of sorts the engine runs, with the same message.
- `search.aggregates.*.filters` must be an array: "The search.aggregates.0.filters field must be an array." Before, it was not validated.
- What a refused list holds is not checked: the `viewAny` check of a relation it names and the `create` check of a mutation entry in it do not run. This covers a list over its limit or over a total, a search list, a `mutate` list or a to-many relation given as an object, `includes` at `depths.includes`, `relations` at `depths.mutations`, a `nested` group at `depths.filters` and the body of an include that goes deeper than `depths.includes`. Such a request gets the 422 of the refused key, where it got a 403 before. The relation of an include that goes too deep is still checked on each of its levels, so a denied relation there still gives a 403; a relation named only in its body is not checked. The relation of a relation group whose `nested` is refused is still checked. A relation named only in that `nested` is not. The checks of the other parts of the payload still run first, and one that fails there still gives a 403.
- With Precognition, a list over its limit reports on its own key. A `Precognition-Validate-Only` name below it, such as `search.filters.0.field`, gets `204`, so name the list or an ancestor, such as `search.filters` or `search`.
- The package now renders some errors itself, so a custom message keyed by their rule no longer applies, and `$validator->failed()` reports `Lomkit\Rest\Validation\Rejected` or `Lomkit\Rest\Validation\ExistingKey` instead of Laravel's rule:
  - `max` on each list that takes a `rest.limits.sizes` value, `search.sorts` next to an engine text included, and on the `resources` of an action: `Rejected` instead of `Max`;
  - `list` on `resources`: `Rejected` instead of `List`;
  - `distinct` on the keys of `resources`: `Rejected` instead of `Distinct`;
  - `exists` on the keys of `resources` and of `mutate`: `ExistingKey` instead of `Exists`;
  - the not-writable errors of `attributes` and `pivot` in `mutate`, and a name in `relations` that the resource does not declare: `Rejected` instead of the package rules `WritableAttributes` and `ArrayWithKey`;
  - `list` on the lists of a search and on `mutate`, its to-many relations and its key lists, `prohibited` on a key that the package does not know in a search object, in an entry of the `fields` of an action or in a mutate entry, the `*` key error on such an entry, and the include depth error of a dotted include: `Rejected`. These errors are new.
- Laravel's `list` rule still checks the `fields` of actions and instructions, the values of a filter and the `parameters` of a scope, and Laravel's `max` rule still limits the `parameters` of a scope. Custom messages keyed by these rules still apply there. A filter value or the `fields` of an action or of an instruction given as an object only gets the `list` message: its items are no longer checked. The other rules of a search key do not change, so custom messages keyed by them still apply.
- Laravel's lines `validation.max.array`, `validation.list`, `validation.distinct`, `validation.exists`, `validation.prohibited` and the type lines still apply. The two total texts and the include depth text are JSON translation keys. The texts of the include alias rule and of the rule that checks a filter value on a search engine are JSON translation keys too, with the same English text. So are the not-writable text of `mutate` ("The :key key of the :attribute field is not writable on :operation."), the text of a relation name that the resource does not declare ("The :key key is not valid for the :attribute field.") and the relation text of includes and aggregates ("The :attribute field is not a valid or allowed relation for this resource."). These texts name their field with `:attribute`: "The slug key of the mutate.0.attributes field is not writable on create." and "The search.includes.0.relation field is not a valid or allowed relation for this resource.".
- These limits are not the page sizes of a resource, which `limits()` returns.

Resource keys:

- `destroy`, `restore`, `forceDestroy` and the actions with the `Resources` target read `resources` the same way. `resources` is required and must be a list of keys typed like the resource's `ID` field.
- The keys are checked with one query per 1,000 keys, after authorization, and only when `resources` is validated: a `Precognition-Validate-Only` request that leaves `resources` out runs no key query.
- The query applies the `perimeterQuery()` of the resource and the global scopes of the model, with the trashed rows on `restore` and `forceDestroy`.
- The `ID` field sets the type of the keys, whatever the `$keyType` of the model. Integer keys are written into the query as integers, and other keys are bound as sent. UUID and ULID keys that differ only by case are the same key. A key of the `string` type is found only when the query returns it exactly as sent. An `ID` whose key type does not match the column, such as `keyType('string')` on an integer column, finds no key.
- A key that the query does not return gives a 422, "The selected resources.0 is invalid.":
  - on `destroy`, a key that `perimeterQuery()` or a global scope leaves out, a trashed key included. Before, it passed validation and was skipped;
  - on `restore` and `forceDestroy`, a key that does not exist or that `perimeterQuery()` leaves out. Before, a key that the query of the endpoint left out was skipped;
  - on an action with the `Resources` target, a key that `perimeterQuery()` leaves out. Before, it was skipped and `impacted` was lower.
- A repeated key gives "The resources.0 field has a duplicate value." on every copy, on `restore`, `forceDestroy` and the actions with the `Resources` target too. `destroy` already gave it. On every endpoint, two UUID or ULID keys that differ only by case are repeated.
- On `destroy`, a repeated value that is not a valid key gets its type message only. Before, it also got "has a duplicate value.".
- An object in place of a list gives "The resources field must be a list.", on every endpoint that takes keys.
- `destroy`, `restore` and `forceDestroy` accept at most `rest.limits.sizes.resources` keys: "The resources field must not have more than 1000 items." An action with the `Resources` target over its `$maxResources` gets only the error on `resources`. Before, every key was also checked.
- On an action with the `Resources` target, a key of the wrong type gives "The resources.0 field must be an integer." (or a string, a UUID or a ULID, by key type). Before, it gave "The selected resources.0 is invalid.", and a 500 on PostgreSQL.
- A custom message for `exists` or `distinct` on `resources.*` no longer applies, see "Limits" above.
- `OperateRequest::rules()`, and the `rules()` of `DestroyRequest`, `RestoreRequest` and `ForceDestroyRequest`, return rules on `resources.0`, `resources.1`, … instead of `resources.*`.
- On `destroy`, `restore` and `forceDestroy`, `perimeterQuery()` runs once, during validation, before the endpoint writes: the key query loads the models that the endpoint then deletes or restores, and its events receive them. Before, the query of the endpoint ran a second time to load the models. `$request->resource` is set in `rules()`.
- The deletion endpoints check their keys and load their models with one query per 1,000 keys, `select *` with the keys of `resources`. Before, they ran a second query to load the models.
- An action with the `Resources` target loads its models through the search query of the resource, limited to the parsed keys in the same way. Before, the `$keyType` of the model typed the keys of that load: with UUID keys on a model that keeps the default `int` key type, an action could act on other rows on MySQL, and failed on PostgreSQL. A queued or batched action loads its models again in the worker by key, typed by the `ID` field of the resource in the same way.
- `OperateRequest::operateRules()` and `targetKeys()` are removed. `$request->payload()->keys()` returns the parsed keys of an action with the `Resources` target, a `KeyList`, see "Requests" above. `payload()` throws a `LogicException` before the request passed validation, and `keys()` throws "The request has no resource keys." on an action with another target. An action with the `Resources` target reads them. `OperateRequest` no longer uses the `ParsesSearch` and `ParsesResourceKeys` traits, and keeps `keysQuery()`. Every package request records its validation pass in `passedValidation()`, so an override of `passedValidation()` must call the parent.
- `DestroyRequest`, `RestoreRequest` and `ForceDestroyRequest` have `payload(): KeyList` (`Lomkit\Rest\Validation\KeyList`) and `keysQuery(Resource $resource): Illuminate\Database\Eloquent\Builder`. The `keys` of a `KeyList` hold the parsed keys, and its `constrain()` method limits an Eloquent query to them. Its `rows()` method returns the models that the key query loaded, in the order of `resources`, on `destroy`, `restore` and `forceDestroy`. An action with the `Resources` target checks its keys without loading the models: there, `rows()` throws a `LogicException`. `payload()` throws a `LogicException` before the request passed validation. The request records its validation pass in `passedValidation()`, as every package request does, so an override of `passedValidation()` must call the parent.
- The `ValidatesResourceKeys` trait and the `destroyRules()`, `restoreRules()` and `forceDestroyRules()` methods are removed. Override `rules()` and call the parent instead. A `resources.*` rule added there runs on each key, next to the rules of the package, and does not replace them. It does not run when `resources` is over its limit or is not a list.
- The `key` of a mutation entry is typed by the `ID` field of its resource and checked in the same way, through the `perimeterQuery()` of its resource, see "Mutate payload" above. A repeated key is accepted there, and two forms of one UUID or ULID key that differ only by case are checked apart.

### Authorization

Each request now has one authorizer, which the resources of the request share: `$request->context()->authorizer()`, a `Lomkit\Rest\Authorization\Authorizer`.

Within a request:

- Each distinct check runs once. A check is an ability on a resource for a model, or for a model class, plus the related model of an attach or detach check. The policy runs on the first check, and the later checks of the request reuse its decision.
- `GateEvaluated` listeners and `Gate::after()` callbacks see one evaluation per distinct check per request, not one per call. A decision read from the cache between requests dispatches no `GateEvaluated` event either.
- After each write the package performs (mutate creates and updates, relation writes, destroy, restore and force destroy), the decisions on the written model are dropped, so a later check in the same request sees the new state. A row that a mutation names without writing it, such as the target of an `attach` or a `detach`, or an `update` that changes nothing, keeps its decisions; a row whose entry sends `relations` drops them once its relations are written. Before, every row that a mutation named dropped its decisions, and its policies ran again. If a hook of yours writes a model and a later check in the request depends on it, call `$request->context()->authorizer()->forget($model)` after the write.
- Policies run with `Gate::forUser($request->user())`, or with the authenticated user when the request has none. `Gate::before()` and `Gate::after()` callbacks receive that user.
- The authorizer belongs to one user. When the user or the default guard changes, for example in a test or a long-running process that reuses a resource, the next check starts a new authorizer for the current user, and the decisions of the previous user are not reused.

Checks before validation:

- Each request checks the abilities of its endpoint, then the resources its payload reaches, before it validates the payload and before any query. A denied check answers with the status of the policy's response, `403` by default, even when the payload is invalid: the `403` now comes before the `422`. This also applies to Precognition requests, with or without `Precognition-Validate-Only`, which answered `204` or `422` before.
- The `authorize()` method of a request still runs first. When it passes, the endpoint abilities and the reached resources must pass too.
- The endpoint abilities are `viewAny` for `details`, `search`, `mutate`, `destroy`, `restore` and `forceDestroy`, before the checks of each row and each creation: a user who cannot list a resource gets a `403` on its writes. To let such users write, return `[]` from `abilities()` for these endpoints, see below. `operate` is described under "Actions" below.
- To change them for one resource, override `abilities(Endpoint $endpoint): array` on the resource (`Lomkit\Rest\Http\Routing\Endpoint`), for example to require `deleteAny` on `Endpoint::Destroy`. The list replaces the default of the endpoint: a resource that returns `['export']` for `Endpoint::Search` no longer checks `viewAny` there, and its search runs without it. To add an ability, keep `viewAny` in the list, as in `['viewAny', 'export']`. `Endpoint::Operate` is the exception: its list adds to `viewAny`. A resource that already defines a method named `abilities()` must give it this signature.
- `details` checks `viewAny` before the resource's `describing()` event runs, so the event does not run for a user who cannot list the resource.
- Every resource the payload reaches needs `viewAny` on that resource: each level of an include (`team.replies` needs `viewAny` on the teams and on the replies), each level of an aggregate, each level of a filter on a relation, nested filters included, the relation of a relation group, each relation of a sort on a relation field (`author.last_name` needs `viewAny` on the authors), and a filter on a pivot column (`tags.pivot.position` needs `viewAny` on the tags). The same applies to the `search` of an action, and to the `response` block of a mutation, which also needs `viewAny` on the resource of the endpoint. A filter on a relation did not check the related resource before, and an include was checked only when the query returned rows.
- Each `create` in `mutate`, at the root or under a relation, needs `create` on the created resource. It is checked once per resource class and request, before validation, with the model class as its argument, as `Gate::inspect('create', Model::class)` does. Each created row is also checked with its draft, see "Row checks of `mutate`" below. A `create` policy method whose second parameter has no default fails on this first check: declare it `?Post $draft = null`. A creation under a relation that the package refuses, such as a `HasManyThrough` relation or a relation marked `prohibitedOnCreation()`, is checked before the `422` as well.

Row checks of `mutate`:

- Every row check of a mutation runs in one batch, inside its transaction, before `mutatingAll()` and the first write: no row is written, and no model event, observer, `mutatingAll()` event or `mutating()` hook runs, before a `403`. Before, each row was checked right before its own write, and the rows written before a denial ran their events and hooks.
- Each created row is checked with `create` and its draft: an unsaved model filled with the validated attributes of its entry (through the fields, without their `fillUsing()` callbacks) and with the keys that its owner relations and its parent relation set when these rows exist. A policy declares it as an optional parameter:

  ```php
  public function create(User $user, ?Post $draft = null): bool
  {
      return $draft === null || $draft->team_id === $user->team_id;
  }
  ```

  A `create(User $user)` method keeps working: Laravel passes the draft as an extra argument. The class-level `create` check of the request still runs first, so the policy is called once per resource class, then once per created row.
- Each updated row is checked with `update`, the stored row and a `Lomkit\Rest\Authorization\Changes` object of the values that the write sets: the validated attributes, and the keys that its relations set when these rows exist. `has($key)`, `get($key)` and `keys()` read it:

  ```php
  public function update(User $user, Post $post, ?Changes $changes = null): Response
  {
      return $changes?->get('team_id') !== null && $changes->get('team_id') !== $user->team_id
          ? Response::deny('Posts cannot move to another team.')
          : Response::allow();
  }
  ```

  When several entries write the same row, each later entry is checked against the row with the changes of the entries before it.
- A row that an entry names without writing it, the target of an `attach`, a `detach`, a `sync` or a `toggle` without attributes, is checked with `view`. A row moved by an entry under a `HasOne`, `HasOneOfMany`, `HasMany`, `MorphOne`, `MorphOneOfMany` or `MorphMany` relation is checked with `update` and its new keys. Before, it was checked with `view`.
- The link of a relation is checked with the ability that the relation declares, on the policy of the parent row, with the parent and the related row:

  ```php
  BelongsToMany::make('tags', TagResource::class)
      ->abilities(attach: 'attachTag', detach: 'detachTag'),
  ```

  `attach` covers `create`, `update` and `attach` entries and the rows that `sync` and `toggle` attach; `detach` covers `detach` entries and the rows that `sync` and `toggle` detach. Without a declared ability, the link is checked with `update` on the parent row, with its changes; for a parent that the request creates, its `create` check covers it. `Gate::before()` applies to both. A declared ability that no policy method answers is denied. Before, the package called `attach{Model}` or `detach{Model}` of the parent policy, named after the related model, and allowed the link when the method was missing.
- A link that involves a row the request creates is checked once that row is saved, right before the link is written: the check of a declared ability may then run after earlier writes of the request, which a denial rolls back. When the created row's own insert writes the link, the check receives the row filled and not yet saved.
- `sync` and `toggle` compare the keys they send with the attached rows that the client can write, read once per parent row and relation through the `perimeterQuery()` of the related resource, before any write. Each row that they attach or detach is checked. A row outside this query, or one that a global scope such as `SoftDeletes` leaves out, stays attached. Before, `sync` detached every attached row the keys left out, and the checks ran after the pivot write.
- The policies see the rows as they were before the request: a policy that reads the database runs before the writes of the request.
- A denied check throws `AuthorizationDenied` with every denial of the batch, each with the path of its entry, the ability and the key of its row; a denied link names the key of the related row. The first denial in the order of the request gives the message and the status.

Actions:

- An unknown action answers `404` to a user allowed `viewAny` on the resource, and `403` to the others. So does an action that has no route, see [Routing](#routing).
- A known action checks, before validation and in this order: `viewAny` on the resource, the abilities that the resource's `abilities()` returns for `Endpoint::Operate` (none by default), then the abilities that the action's `abilities()` returns. These abilities add to `viewAny` and never replace it.
- `Action::abilities(): array` returns `['viewAny']` by default, for every action, whatever its target. An action that returns `['export']` checks `viewAny`, then `export`; listing `viewAny` as well, as in `['viewAny', 'export']`, checks the same abilities. An action that already defines a method named `abilities()` must give it this signature.
- An action whose `abilities()` returns `[]` runs without any of these checks: neither `viewAny` nor the resource's abilities for `Endpoint::Operate`, and the search of its models does not check `viewAny` on the resource either. Use it for an action that any user may run, guests included. The resources that its `search` reaches through includes, aggregates and filters on relations still need `viewAny`.
- `Action::entryAbilities()` returns the abilities that the route of the action checks: `[]` when `abilities()` returns `[]`, otherwise `viewAny`, the resource's abilities for `Endpoint::Operate`, then `abilities()`, each once. `Metric::entryAbilities()` does the same with `Endpoint::Metric`. Both are final: override `abilities()`. The details answer `authorized` from them.
- After these checks, an action that returns an ability from `authorizeEach()` checks it on each row, see [Actions](#actions).

Denials:

- A check made before validation (an endpoint ability or a reached resource), a row check of `destroy`, `restore` or `forceDestroy`, or a row check of an action, that is denied throws `Lomkit\Rest\Exceptions\AuthorizationDenied`, which extends Laravel's `Illuminate\Auth\Access\AuthorizationException`. It holds every denial of these checks, at most 50, as `Lomkit\Rest\Authorization\Denial` objects in `$exception->denials`, each with the path in the payload (`search.includes.0`, `mutate.1.relations.team`, `resources.1`, `search` for a row of an action's search, or an empty string for the endpoint), the ability, the resource class, the key of the model when the check is on a model, and the message.
- Its message, code and status are those of the first denial, in the order of the request's rules, or, for the rows of `destroy`, `restore` and `forceDestroy`, in the order of `resources`, and for the rows of an action, in the order the action reads them. The response body stays `{"message": ...}`, and a policy that returns `Response::denyAsNotFound()` still answers `404`.
- The row checks of `mutate` throw `AuthorizationDenied` too, with every denial of the request (see "Row checks of `mutate`"). Before, they threw Laravel's `AuthorizationException` with the first denial.
- `destroy`, `restore` and `forceDestroy` check `delete`, `restore` or `forceDelete` on every row before they write any row. A denied row gives a 403 that holds every denied row, at most 50, each under `resources.<index>` with the key of its row, and no hook of a row (`destroying()`, `restoring()`, `forceDestroying()` or the hook after the write), no write and no model event runs; `destroyingAll()`, `restoringAll()` and `forceDestroyingAll()` run after the checks, so a denied row runs no event. Before, the first denied row stopped the checks. The rows are checked, written and returned in the order of `resources`. Before, they came in the order that the database returned them, which differs between databases.
- To add the denials to the JSON body, register the renderer in `bootstrap/app.php`. It adds a `denials` list of `{path, ability, key, message}`, where `key` only appears for a model, to every `AuthorizationDenied` except a `404`, and leaves the other responses unchanged:

  ```php
  ->withExceptions(function (Exceptions $exceptions): void {
      $exceptions->render(new \Lomkit\Rest\Exceptions\DenialsRenderer);
  })
  ```
- `Authorizer::authorizeAll()` and `Authorizer::authorizeEach()` throw `AuthorizationDenied` with every denial. `authorizeEach()` checks each model through the `authorizedTo()` of the resource. Before, both threw Laravel's `AuthorizationException` with the first denial.

Checks the request did not plan:

- The queries still check `viewAny` on each resource they search, and `create` on each resource they create. A check that the request already made before validation is reused; another one is evaluated when the query runs, or, for the `viewAny` of each level of a search, when the search is planned.
- The resource of the endpoint is the exception: its endpoint abilities stand in for `viewAny`. When they leave `viewAny` out (by default for `mutate`, `destroy`, `restore` and `forceDestroy`, through an override of the resource's `abilities()`, or for an action whose `abilities()` returns `[]`), the queries do not check it on that resource, and strict mode does not report it. The other resources the queries search are checked, or reported, as above.
- With `authorizations.strict` set to `true` (`false` by default, see [Configuration](#configuration)), a check that the request did not make before validation throws `Lomkit\Rest\Exceptions\UnplannedCheckException` instead. Turn it on in your test suite to find a resource that the request reaches without checking it first.
- `Lomkit\Rest\Search\Plan\SearchPlanner` evaluates `viewAny` on each level it plans, when `paged()`, `all()` or `plan()` builds the plan, even an include level whose parents have no row. In strict mode, plan it first: `$resource->context()->authorizer()->plan([Check::onClass($resource, 'viewAny')])` (`Lomkit\Rest\Authorization\Check`).

Cache:

- A resource lists in `authorizationCache()` the abilities whose decisions are kept between requests, with the number of seconds to keep each one:

  ```php
  public function authorizationCache(): array
  {
      return ['viewAny' => 300, 'view' => 60];
  }
  ```

  The other abilities run the policy in each request. A resource that declares nothing keeps no decision. Before, when the cache was on, every ability but `create` of every resource was kept for `authorizations.cache.default` minutes.
- `cacheAuthorizationFor()` and `authorizations.cache.default` are removed. Move the duration to `authorizationCache()`, per ability, in seconds. A seconds value that is not an integer of 1 or more throws `Lomkit\Rest\Exceptions\InvalidResourceException` when a check first reads the declaration: "The resource [App\Rest\Resources\PostResource] caches the ability [view] in authorizationCache() without a valid duration. Give an integer number of seconds of 1 or more, for example ['view' => 300]."
- The resource of the check declares the ability: the resource of the endpoint or of the reached relation for `viewAny` and `create`, the resource of the row for its gates and its row checks, and the parent resource for the `attach` and `detach` abilities of a relation.
- A declared ability is read from the cache wherever it is checked: the gates of a response, the checks made before validation, the row checks of `mutate`, `destroy`, `restore` and `forceDestroy`, and the row checks of actions. A cached `delete` on a row lets `destroy` delete that row without calling the policy while the row keeps its `updated_at`.
- `create` can be declared: the check on the model class is kept, including the `authorized_to_create` gate. The check on each record that `mutate` creates is not kept, nor are the `update` checks of `mutate`, which carry the changes of their entry.
- Denials are kept like the other decisions, with their message and status.
- The decisions go to the store that `authorizations.cache.store` names, or to the default store. A store that is not defined throws "Cache store [name] is not defined." on the first check of a declared ability. When no resource declares an ability, the package calls no cache store.
- A decision is stored as an array of scalars (`allowed`, `message`, `code` and `status`), not as a `Response` object, so it reads back with any store, including the `file` and `database` stores with `cache.serializable_classes` set to `false`.
- The cache key holds the guard, the user class and key, the resource class, the ability, the class, key and `updated_at` of each model, the values of `authorizationCacheContext()`, and the stamps that `Rest::forgetAuthorizationsFor()` and `Rest::forgetAuthorizations()` renew. Entries written under the previous keys are not read anymore and expire on their own.
- A decision on a row is kept under the row's `updated_at`, so a change made through Eloquent is checked again in the next request. It is not kept when the model has no `updated_at` (`$timestamps = false`, a `null` value): the decisions on the model class, such as `viewAny` and `create`, are still kept. Add timestamps to a model to keep the decisions on its rows.
- It is not kept either when the row's `updated_at` is not before the second in which the request started: `timestamps()` keeps whole seconds, so two writes in one second leave the same `updated_at`. Such a row is checked again in the next request. The servers that run your application and Eloquent must share the clock. A change made without Eloquent that leaves `updated_at` alone (`DB::table()->update()`, a trigger, a raw query) shows once the entry expires or the user is forgotten.
- A search selects the `updated_at` column of a level when the level asks a row gate that its resource keeps, so that the decision can be kept. The column is not rendered.
- A check on a row that the request wrote is neither read from nor written to the cache, so a write that rolls back leaves no decision behind.
- The kept decisions of a group of checks are read from the cache store in one call, and the decisions that the group evaluates are written in one call per number of seconds once the group is done. The gates of a response, at every level of its includes, are one group; the checks made before validation are two (the endpoint, then the resources it reaches); the row checks of `mutate`, `destroy`, `restore` and `forceDestroy` are one; the row checks of an action are one per chunk. `$request->context()->authorizer()->batch($checks, fn () => ...)` groups checks of your own the same way: it reads the kept decisions of `$checks`, a list of `Lomkit\Rest\Authorization\Check`, runs the closure, and writes what the closure evaluated when it returns or throws. An override of `authorizedTo()` still runs for each check.
- `authorizationCacheContext()` adds values of your own to the key of every decision that the resource keeps, for example the team of the user:

  ```php
  public function authorizationCacheContext(): array
  {
      return ['team' => $this->request()->user()?->current_team_id];
  }
  ```

  A value must be an integer, a string, a boolean or `null`. Another value throws `Lomkit\Rest\Exceptions\InvalidResourceException`: "The resource [App\Rest\Resources\PostResource] returns a value for the key [team] from authorizationCacheContext() that is not an integer, a string, a boolean or null. Give a scalar value, for example ['team' => 1]." A value that the context leaves out, such as a header, is not part of the key.
- With several tenants whose users share identifiers, such as a database per tenant or users shared between tenants, the key holds the user class and key but not the tenant: a decision kept for user #7 in one tenant is read for user #7 in another. Add the tenant to `authorizationCacheContext()` of the base resource of your application:

  ```php
  public function authorizationCacheContext(): array
  {
      return ['tenant' => tenant()?->getTenantKey()];
  }
  ```

  Or name in `authorizations.cache.store` a store that is already per tenant, such as a `database` store on the tenant connection or a Redis store with a prefix per tenant. The cache tags that the `CacheTenancyBootstrapper` of stancl/tenancy 3 adds do not reach this store: the package calls `store()` on the cache manager. The context also keys the metric results, `cacheScope('global')` included, see [Metrics](#metrics). `Rest::forgetAuthorizationsFor($user)` drops the decisions of the user in every tenant that shares the store.
- A model whose key is cast to an enum is keyed by the value of the enum. Checks on a model that is not saved yet, or whose key is not a string, an integer or an enum, are neither cached nor shared with another check of the request.
- `Rest::forgetAuthorizationsFor($user)` drops the kept decisions and the kept metric results of a user, on every guard and in every request that follows. Call it where your application changes what a policy or a `Gate::before()` callback reads about the user, such as a role or a team: the answers of `Gate::before()` and `Gate::after()` are kept like the policy's. It works on every cache store and writes even when `authorizations.cache.enabled` is `false`.
- `Rest::forgetAuthorizations()` drops the kept decisions and metric results of every user and of guests, for example after a deploy that changes a policy. Guests share one kept decision per guard.
- Otherwise a kept decision stays for the seconds of its ability: a change to a policy or to a `Gate::before()` callback shows once the entry expires.
- `Resource::getAuthorizationCacheKey()` is removed: the package builds the key.
- `isAuthorizationCacheEnabled()` and the `Lomkit\Rest\Concerns\Resource\DisableAuthorizationsCache` trait are removed. To turn the cache off for one resource, declare no ability in its `authorizationCache()`, or remove the override. When `authorizations.cache.enabled` is `false`, no resource uses the cache.
- A metric that declares `cacheFor()` keeps its result in the same store, under a key that holds the user (or, with `cacheScope('global')`, the SQL and bindings of `perimeterQuery()` instead), the resource, the metric and its settings, the `range`, `timezone` and `search` of the request, the values of `authorizationCacheContext()` and the same stamps, see [Metrics](#metrics).

Resource methods:

- The authorization methods declare native types. An override must declare compatible types:
  - `authorizeTo(string $ability, Model|string $model, array $arguments = []): void` and `authorizedTo(string $ability, Model|string $model, array $arguments = []): Response`, where `$arguments` holds the models and the `Changes` passed to the policy after the model;
  - `authorizeToAttach()`, `authorizedToAttach()`, `authorizeToDetach()`, `authorizedToDetach()`, `authorizeToPerformActionOnRelationship()` and `authorizedToPerformActionOnRelationship()` are removed: declare the ability of a link on its relation with `abilities(attach:, detach:)`.
- `authorizeTo()` calls `authorizedTo()`, so an override of `authorizedTo()` applies to the endpoints as well as to the gates. The checks made before validation, on the endpoint and on each reached resource, also go through the `authorizedTo()` of their resource. So do the row checks of `mutate`, `destroy`, `restore` and `forceDestroy`, which no longer call `authorizeTo()`: an override of `authorizeTo()` does not apply to them.
- `authorizedTo()` returns a new `Illuminate\Auth\Access\Response` built from the decision, with or without the cache. It keeps the result, the message and the status of the policy's response, and its code when the code is an integer or a string. It does not keep a code of another type, such as an enum or an object, or an instance of your own `Response` subclass.

### Responses

The package renders the rows of a response from the plan of the search: the `selects`, `gates`, `aggregates` and `includes` of `$request->payload()`, at every level.

- A custom `Response` class keeps `map(Model $model, array $responseModel): array` only. It receives each row of the page or of the list, and each record of a mutation, as the package rendered it, and returns the row to send. `modelToResponse()`, `buildGatesForModel()` and `requestArrayForRelation()` are removed.
- `Lomkit\Rest\Http\Response::resource()` is replaced by `plan(QueryPlan $plan)`, which takes the `Lomkit\Rest\Search\Plan\QueryPlan` of the rows. `responsable()` takes an `Illuminate\Contracts\Pagination\LengthAwarePaginator`, a `Collection` of models or one model. `plan()` and `responsable()` return the response.
- `rows(array $models): array` renders models with the plan of the response and `map()`, and keeps their keys. The records of a mutation use it, see [Writes](#writes). A `Response` subclass that already defines a method named `rows()` must give it this signature.
- `toResponse()` returns an `Illuminate\Http\JsonResponse`. A page holds `current_page`, `data`, `from`, `last_page`, `per_page`, `to`, `total` and `meta`, and a list or one model holds `data` and `meta`. The pages of a resource that sets `$maxTotal` add `total_capped` after `total`, see [Search](#search).
- `meta` is always an object: `{}` when it holds nothing. The `pivot` of a row is an object too: `{}` when its relation returns no pivot field. Before, both were `[]` when empty.
- A paginator that `paginate()` returns renders as a page when it implements `Illuminate\Contracts\Pagination\LengthAwarePaginator`, whatever its class. Before, only an `Illuminate\Pagination\LengthAwarePaginator` did.
- `Lomkit\Rest\Pagination\LengthAwarePaginator` declares native types: `__construct(array $items, int $total, int $perPage, int $currentPage, array $options = [], array $meta = [], ?bool $totalCapped = null)`. Its items are the rendered rows, and `toArray()` adds `total_capped` when `$totalCapped` is not `null`. The default `paginate()` of a resource that sets `$maxTotal` returns a `Lomkit\Rest\Pagination\CappedPaginator`, whose `totalCapped` fills it.
- The classes of `Lomkit\Rest\Http\Serialization` render the rows: `RowSerializer` renders the rows of one level of the plan with `RowFields`, `GateTable`, `RelationRenderer` and `PivotRenderer`, and `PageEnvelope` or `PlainEnvelope` wraps them. Each gate is still read through the `authorizedTo()` of the resource, except a write gate on a row that its endpoint does not reach, see "Gates" below.
- `RowFields` converts only the columns that a row returns: the casts, the accessors and the date format of the other attributes of the model no longer run when a row is rendered, and the appended attributes (`$appends`) never do, see [Selects, sorts and aggregates](#selects-sorts-and-aggregates).
- A row returns the relations that its search includes, and no other relation. A relation that the model loads by itself, through `$with`, a `with()` in a relation method, `perimeterQuery()`, a scope, an instruction or a hook, is still loaded, so the policies, the hooks and `map()` can read it, but the response no longer returns it: add it to `includes` to return it. The rows of `destroy`, `restore` and `forceDestroy` return no relation. A loaded relation that the resource does not declare no longer gives a 500. The `pivot` of a row reached through a `BelongsToMany`, `MorphToMany` or `MorphedByMany` include is still returned. Before, each loaded relation was returned with the selectable fields of its resource.

MorphTo includes:

- A `MorphTo` relation declares a list of targets: `MorphTo::make('labelable', [BookResource::class, ShelfResource::class])`. Each target is keyed by its morph type, the `getMorphClass()` of its model: the morph-map alias, or the class name without a map. A morph map is recommended; adding one to an existing app needs the stored types migrated (`UPDATE labels SET labelable_type = 'book' WHERE labelable_type = 'App\\Models\\Book'`), because a stored class name no longer matches. A declaration with one resource is the one-target case. `Relation::resource()` throws a `LogicException` on a relation with several targets: use `targets()` and `resourceOf()` of `Lomkit\Rest\Contracts\PolymorphicRelation`, which `MorphTo` implements. The model keeps one `morphTo()` method; relations declared per target through a `morphTo('<name>', ...)` with their own name keep working as one-target relations.
- Each row that a `MorphTo` include returns starts with its type, under the `@type` key: the type that the parent row stores, which is the morph-map alias of the model, or its class name without an alias. `morph.type_key` in `config/rest.php` renames the key. Each `MorphTo` include carries it, aliased or nested, and no other row does. A target resource that declares a field named like the key throws `Lomkit\Rest\Exceptions\InvalidResourceException` when a request resolves that target. For example, a label with the book it is attached to:

  ```json
  {"id": 1, "text": "classic", "labelable": {"@type": "book", "id": 1, "title": "Dune"}}
  ```

- The parent rows are grouped by the exact type that they store. A row whose type is not a target of the relation, or not a listed type, returns `null` for the include, and its type runs no query: the type of another model, a type that differs only by case, a class name stored before a morph map, or any other string. A row without a type or without a key returns `null` too. Before, the type of another model returned that model with the fields of the declared resource, a type that differs only by case returned the model, and a type that is not a class gave a 500.
- The targets of one type load with one query, which applies the search of the include: its filters, scopes, instructions, aggregates and includes, aliased ones included, and the `perimeterQuery()` of its resource. Before, a filter or an aggregate of a `MorphTo` include gave a 500, and an aliased include inside an aliased `MorphTo` include was left out.
- An include of a `MorphTo` with several targets sets its search for each type under `types`: `{"relation": "labelable", "types": {"book": {"selects": [{"field": "title"}]}, "shelf": {}}}`. A type block takes `selects`, `filters`, `scopes`, `includes`, `aggregates`, `instructions` and `gates`. Only the listed types load: a row of another type returns `null`, and the user needs `viewAny` on the listed types only. Without `types`, every target loads with its default fields, and the user needs `viewAny` on each of them, else a 403 before any query. The flat keys give a 422 there: `The search.includes.0.selects field is prohibited: this relation has several types. Set it for each type, under types.` With one target, the flat keys work as before, and `types` with its one type is accepted, but not both. A dotted include through a relation with several targets (`labelable.loan`) gives a 422; include `loan` in the type block instead. A dotted include that ends on it (`labels.labelable`) takes `types`.
- The query of the targets has no order and no limit. `sorts` and `limit` on a `MorphTo` include give a 422, see [Search](#search).
- A `MorphTo` include loads after the query of its rows, as an aliased include does, so it comes after the includes without an alias in the row.
- `Lomkit\Rest\Search\Plan\SearchPlanner` plans a `MorphTo` include, aliased or not, as a `Lomkit\Rest\Search\Plan\MorphToIncludeClause`, which holds a `QueryPlan` per type. `MorphToSerializer` renders its rows; it implements `Lomkit\Rest\Http\Serialization\RowRenderer`, as `RowSerializer` does. `Lomkit\Rest\Relations\MorphTo::columns()` returns the type and key columns of the relation, which the details read too.

Gates:

- Each answer of `gates` is an object that holds `allowed` and `message`: the message of the policy's response, or `null`. The `create` answer in `meta` has the same form. For example, a row with the `view` and `update` gates, when the policy denies the update with `Response::deny('Closed tickets are read-only.')`:

  ```json
  {"id": 2, "gates": {"authorized_to_view": {"allowed": true, "message": null}, "authorized_to_update": {"allowed": false, "message": "Closed tickets are read-only."}}}
  ```

  Before, each answer was `true` or `false` unless `gates.message.enabled` was `true`, and that format triggered a deprecation. `gates.message.enabled` and `Lomkit\Rest\Config\RestConfig::$gateMessages` are removed, see [Configuration](#configuration).
- A row whose `gates` hold no answer returns `{}`: with `"gates": []`, or with `create` alone, whose answer goes to `meta`. Before, it returned `[]`. A search without `gates` returns no `gates` key, and `meta` is `{}` without the `create` gate.
- `update`, `delete`, `restore` and `forceDelete` answer for the rows that their endpoint reaches. A row that `mutate`, `destroy`, `restore` or `forceDestroy` would refuse with a 422 answers `{"allowed": false, "message": null}`, and its policy is not called:
  - a row of a search engine page that `perimeterQuery()` leaves out. The page comes from the index, see [Search engines](#search-engines), so the package checks its rows against `perimeterQuery()`, with one query per page, or two when the page holds trashed rows and `gates` lists `restore` or `forceDelete`;
  - a trashed row, for `update` and `delete`, unless `perimeterQuery()` takes trashed rows in: `mutate` and `destroy` do not reach trashed rows. A search with `trashed` and one of these gates runs one more query.

  The other rows of a search, of its includes and the records of a mutation are read through `perimeterQuery()` and need no query. `view`, `create` and the gates that a resource declares answer the policy alone. Before, every gate answered the policy alone.
- The gates run `perimeterQuery()` with the request of the response: the search request, or the `mutate` request for the records of a mutation. When `perimeterQuery()` leaves a row out on one endpoint only, by checking `$this->request()->endpoint()`, a search still answers that endpoint's gate from the policy: put a condition that a gate must follow in the policy.
- `map()` receives the answers of a row as an object (`stdClass`) whose properties are the names of the answers: read `$responseModel['gates']->authorized_to_view['allowed']`. Before, it received an array.
- `gates` takes the abilities `view`, `create`, `update`, `delete`, `restore` and `forceDelete`, and the gates that the resource of its level declares (below), each once. `viewAny`, another name or a value that is not a string gives a 422 under its entry, `The selected search.gates.0 is invalid.`, and an ability listed twice gives `The search.gates.0 field has a duplicate value.` on each copy. Before, `viewAny` was accepted and never answered, a repeated ability was answered once, and another name gave the 422 on the whole list, `The selected search.gates is invalid.`
- `create` is accepted at the root of a search only, since its answer goes to `meta`. In an include, at any depth, it gives a 422 under its entry: `The search.includes.0.gates.0 field is prohibited.` Before, it was accepted and left out of the response.
- The search of an action checks `gates` the same way, and the action still returns no answer.
- A `gates` list has no size limit of its own: it holds each ability once at most, and its entries still count in `limits.totals.entries`. `limits.sizes.gates` and `Lomkit\Rest\Validation\ListSizes::$gates` are removed.
- `Lomkit\Rest\Search\SearchNode::$gates` and `Lomkit\Rest\Search\Plan\QueryPlan::$gates` hold `Lomkit\Rest\Search\GateAbility` objects instead of strings. A `GateAbility` holds an `ability`: a case of the `Lomkit\Rest\Authorization\Ability` enum, or the name of a declared gate (`new GateAbility('create')` holds `Ability::Create`). It gives the ability that the policy answers (`name()`), the default name of its answer (`key()`, such as `authorized_to_force_delete`) and whether its answer goes to `meta` (`isClassLevel()`). `Lomkit\Rest\Validation\Search\GatesParser` reads `gates` and accepts the gates of the `Lomkit\Rest\Search\GateVocabulary` that `$resource->definition()->gates()` returns.
- A resource declares more gates in `gates(): array`, which returns the names of policy methods:

  ```php
  public function gates(): array
  {
      return ['download'];
  }
  ```

  A search of that resource then accepts `download` in `gates`, at the root and in each include of that resource, and each row answers it under `authorized_to_download`, the name in snake case, from the `download()` method of the policy, which receives the user and the model. `gates.names` renames it like the other answers. A declared gate is answered on each row, never in `meta`. A policy without the method denies it, with a `null` message, as Laravel does: the package does not read the policies. Each answer goes through `authorizedTo()`, so the policy runs once per row and gate in a request.
- A declared name is the camelCase name of a policy method, listed once, other than `viewAny` and the six abilities above. Another value throws `Lomkit\Rest\Exceptions\InvalidResourceException` when the gates of the resource are first read: by the details, or by a search that lists `gates`. A resource that already defines a method named `gates()` must give it this signature.

### Selects, sorts and aggregates

A search reads each field through its declaration: `writeOnly()`, `selectable(false)`, `virtual()` and `resolveUsing()` decide what a row can return, and `sortable()` and `sortUsing()` decide how the field is sorted.

Loaded columns:

- A search loads only the columns that its rows need, at every level: the columns of the returned fields, the key of the model, the columns that a returned field lists in `requires()`, the columns that the resource lists in `alwaysSelect()`, and the keys that its includes need, on the parent row and on the related rows. Before, each query loaded every column (`select *`). The page of a text search run by a search engine and the records of a mutation (see [Writes](#writes)) load their columns the same way. The rows of an action, the rows of `destroy`, `restore` and `forceDestroy`, and the rows that a mutation looks up still load every column. The page of a search engine also loads the column that `getScoutKeyName()` names, which orders it: a `getScoutKey()` that reads another column needs that column in `alwaysSelect()`.
- A policy of the `gates`, a `resolveUsing()`, `map()` or `searched()` that reads a column that the search did not load throws `Lomkit\Rest\Exceptions\MissingColumnException`, which names the resource, the column and the model, and the search answers `500`. Before, it read `null`, so a gate could answer `allowed: true` on a row that the write endpoints refuse. The package turns on Eloquent's check of missing attributes around these calls only, at every level of the search and for the records of a mutation. A callback set with `Model::handleMissingAttributeViolationUsing()` runs instead of the exception, and the value that it returns is read. An observer, or an accessor read outside these calls, still gets `null`, or a `Illuminate\Database\Eloquent\MissingAttributeException` when the application calls `Model::preventAccessingMissingAttributes()`. List the column in `alwaysSelect()`, or in the `requires()` of the field whose value needs it, or set `search.select_columns` to `false` in `config/rest.php` to load every column again. For example, for a policy that reads the `status` of a ticket:

  ```php
  public function alwaysSelect(): array
  {
      return ['status'];
  }
  ```

- `alwaysSelect(): array` returns the columns of the table of the resource that its searches always load, without returning them. It returns `[]` by default. A resource that already defines a method named `alwaysSelect()` must give it this signature.
- A field that is not a column of the table, such as an accessor or an appended attribute, must be declared `virtual()`, with `resolveUsing()` to return its value: a field that is not virtual is loaded as a column, and the query fails when the table has no such column.
- The appended attributes of a model (`$appends`) are not evaluated on the rows of a response: an appended value is returned only through a field whose `resolveUsing()` reads it. Before, each appended attribute was evaluated on every row, and a field named like one returned its value.
- A relation that the model loads by itself, through `$with` or a `with()` in a scope, a global scope, an instruction or `perimeterQuery()`, still loads: the search adds the keys that it needs on the parent row. A scope or an instruction that calls `select()` keeps its columns, and the search adds its own. A `<table>.*` in that `select()`, alone or next to a subquery such as the count of `$withCount`, is replaced by the columns of the search: list in `alwaysSelect()` the other columns that the application reads. A global scope applies when the query runs, after the search has chosen its columns: a `select()` in a global scope replaces them. A scope that joins another table and reads its columns selects them itself.
- The columns are qualified with the table of the model, as in `"posts"."title"`. The fields of a row come in the order of `selects`, or in the order of `fields()` without `selects`. Before, they came in the order of the columns of the table, as they still do when `search.select_columns` is `false`.
- `Lomkit\Rest\Search\Plan\QueryPlan` holds the columns of its level in `$columns`, a `Lomkit\Rest\Search\Plan\Columns\ColumnSet`, or `null` when the level loads every column. `SearchPlanner` builds them with `ColumnSelection` and with `RelationKeys`, which reads the keys of each kind of Eloquent relation, and its constructor takes a `ColumnSelection` after the `RowSource`. `RowSource` and `KeySet` gain `columns(): array`, the columns that the row source reads on each row: a custom row source or key set adds it. `RestConfig` gains `$selectColumns`.

Selects and rows:

- `selects` accepts the selectable fields only. A field declared `writeOnly()` or `selectable(false)`, and a `virtual()` field without `resolveUsing()`, give a 422 under `search.selects.<i>.field`, as an undeclared field does. The `selects` of an include are checked against the fields of the related resource.
- Add `filterable()` and `sortable()` to each field declared `selectable(false)` that clients still filter, sort or aggregate with `min`, `max`, `avg` or `sum`: without them, such a field gives a 422 in `filters`, `sorts` and these aggregates. `count` and `exists` are unchanged.
- Without `selects`, a row holds the selectable fields of its resource only, at every level of the includes. A `writeOnly()` field is never returned, by any endpoint: declare `writeOnly()` on a field that clients may write but not read. A `virtual()` field is returned only when it declares `resolveUsing()`.
- `destroy`, `restore` and `forceDestroy` refuse `search`: an empty array is accepted, and any other `search` gives a 422 under `search`, "The search field is prohibited." for a value that is not empty and "The search field must be an array." for `null` or a blank string. No row is deleted or restored then. Their response holds the selectable fields of each row, without gates or aggregates.
- `resolveUsing(Closure $callback)` gives a field its value in a row. The callback receives the model of the row, and the response sends the value as the callback returns it: a `Json` field does not decode it. A `virtual()` field with `resolveUsing()` is selectable: `selects` accepts it, a row without `selects` returns it, at every include level, and its details say `"select": true`. A field that is not virtual can declare `resolveUsing()` too: the callback then gives its value instead of the column. In a row, the values of the callbacks come after the columns and the aggregates. The callback runs once for each returned row, so a query in it runs once per row. A `virtual()` field is still sorted and filtered only through `sortUsing()` and `filterUsing()`. `requires()` lists the columns that the callback reads: they load when the field is returned, see "Loaded columns" above.

```php
use Illuminate\Database\Eloquent\Model;

Text::make('display_name')
    ->virtual()
    ->requires(['first_name', 'last_name'])
    ->resolveUsing(fn (Model $author): string => $author->first_name.' '.$author->last_name),
```

Sorts:

- `sorts` accepts the sortable fields of the resource, a field of a to-one relation and the key of an aggregate, see below. A `Json` field, a field declared `sortable(false)` or `writeOnly()`, and a `virtual()` field without `sortUsing()` give a 422 under `search.sorts.<i>.field`. A field declared `selectable(false)` gives the same 422 unless it is declared `sortable()`, with or without `sortUsing()`. A text search that runs on a search engine sorts on the `sortable()` attributes of its `ScoutEngine`, see [Search engines](#search-engines).
- A field with `sortUsing()` is sorted by its callback instead of its column. The callback receives the Eloquent query of the resource, on an include as well, and the direction, `asc` or `desc`. A `virtual()` or `writeOnly()` field with `sortUsing()` is sortable.

  ```php
  use Illuminate\Contracts\Database\Eloquent\Builder;

  Text::make('full_name')
      ->virtual()
      ->sortUsing(fn (Builder $query, string $direction) => $query
          ->orderBy('last_name', $direction)
          ->orderBy('first_name', $direction)),
  ```

- The other fields are ordered by their column, qualified with the table of the model: `posts.views`.
- `direction` accepts the values of `Lomkit\Rest\Fields\SortDirection`, `asc` and `desc`. A sort without a direction is ascending.
- A sort can name a field of a to-one relation of the resource, `<relation>.<field>`: a `BelongsTo`, `HasOne`, `HasOneThrough`, `HasOneOfMany`, `MorphOne` or `MorphOneOfMany` relation. The field must be one that `max` accepts in an aggregate: sortable by its own column, neither `virtual()` nor `writeOnly()`, and not a `Boolean`. A `MorphTo` relation, a relation with several rows, a deeper path such as `author.posts.title` and any other field give a 422 under `search.sorts.<i>.field`, `The selected search.sorts.0.field is invalid.` The fields of a related resource are read only for a sort that names its relation. For example, the posts by the last name of their author:

  ```json
  {"search": {"sorts": [{"field": "author.last_name"}]}}
  ```

- The query orders by a subquery that reads the greatest value of the field among the related rows, so a `HasOne` relation with several rows sorts on the greatest one. The subquery applies the `perimeterQuery()` of the related resource and the global scopes of its model: a missing related row, a soft-deleted one, or one outside that `perimeterQuery()`, sorts as a null value. The database places null values as it does for a column: first in ascending order and last in descending order on SQLite and MySQL, last in ascending order and first in descending order on PostgreSQL.
- A sort can name the key of an aggregate of the same search, its `alias` or `<relation>_<type>_<field>`. The query orders by the subquery of the aggregate, which runs a second time for the order. A `min`, `max`, `avg` or `sum` without a row is a null value, as above. The key of an aggregate that the same level does not request, such as an aggregate of an include, gives the same 422. For example, the authors with the most posts first:

  ```json
  {"search": {"aggregates": [{"relation": "posts", "type": "count"}], "sorts": [{"field": "posts_count", "direction": "desc"}]}}
  ```

- These sorts work at every level that accepts sorts, and within the `limit` of an include. Each one adds a subquery to the order of the query: index the columns that relate the rows. A text search that runs on a search engine still sorts on the `sortable()` attributes of its `ScoutEngine` only: a relation path or an aggregate key gives the same 422 there.
- A sort whose field names a relation needs `viewAny` on each relation of its path, checked before validation as for a filter: a denied check answers 403 under `search.sorts.<i>`, even for a sort that validation would then refuse. `aggregates` are read before `sorts` and `selects`, so the errors of `aggregates` come first in a 422.
- `Builder::sort()` and `applySorts()` are removed. Each sort of the search is a `SortClause` built from its `FieldSort`, which calls `Field::sort()`, a `RelationSortClause` built from its `RelationSort`, an `AggregateSortClause` that orders through the `AggregateClause` of its `AggregateSort` (`AggregateClause::order()`), or the relevance order (`RelevanceClause`) for `@relevance`. `Lomkit\Rest\Search\Plan\RowSource::clauses()` takes the `AggregateClause`s of the level as a third argument.
- `defaultOrderBy()` still orders by the columns it names. The default `Resource::defaultOrderBy()` returns the key of the model in descending order, `[static::newModel()->getKeyName() => 'desc']`, instead of `['id' => 'desc']`: a search without `sorts` on a model whose key is not `id`, such as `code`, now works instead of failing on an unknown `id` column. Each column goes through the model's `qualifyColumn()`: a column that names its table, such as `posts.views`, works. Before, the table was prefixed again.

Aggregates:

- `type` accepts the values of `Lomkit\Rest\Fields\AggregateFunction`: `count`, `min`, `max`, `avg`, `sum` and `exists`. When `type` is missing or unknown, only `type` gets an error.
- `min` and `max` accept a field of the related resource that is sortable by its own column (`Field::isSortableByColumn()`): sortable by default or declared `sortable()`, and neither `virtual()` nor `writeOnly()`. A field declared `selectable(false)` counts only when it is declared `sortable()`. `sortUsing()` does not count, because the aggregate reads the column. A `Json` field that is not declared `sortable()`, a field declared `sortable(false)`, `virtual()` or `writeOnly()`, and an undeclared field give a 422 under `search.aggregates.<i>.field`, with or without `sortUsing()`.
- `min` and `max` refuse a `Boolean` field, and an app type that extends `Boolean`, with the same 422, on every database.
- `min` and `max` accept an `ID` field with UUID keys (a model using `HasUuids`, or `keyType('uuid')`) on every database, and return the same string on each of them. On PostgreSQL the aggregate reads the key as text, which keeps the order of the UUIDs.
- `avg` and `sum` also need a `Number` field: `Number`, `Integer`, or an app type that extends `Number`. Like `min` and `max`, they need a field sortable by its own column, so a field declared `selectable(false)` needs `sortable()` there too. Another field, such as a `Text`, `Date`, `Boolean` or `ID` field, or an app type that extends `Field` directly, gives a 422. To average or sum the values of an app type, make it extend `Number`.
- `count` and `exists` still refuse a `field` with a value. `Builder::aggregate()` is removed: `AggregateClause::of(AggregateNode $node, Clause $constraint, int $position)` applies the aggregate at `$position` in its search. An `AggregateNode` lists in `$through` the relations of its path before the last one. `AggregateClause` applies a node without them through `Lomkit\Rest\Query\RelationAggregate` and a node with them through `Lomkit\Rest\Query\NestedAggregate`, the two implementations of `Lomkit\Rest\Query\AggregateQuery`. The query selects each value under a name of its own, and the response returns it under the `AggregateKey` of the node: the `alias`, or `<relation>_<type>_<field>` with the name of the field in lower case. Before, the value of a field whose name has capital letters was missing from the response, and `count` or `exists` sent with `"field": []` gave a 500.
- The models of the rows no longer hold the value of an aggregate under its key, in a search as in the search of an action: they keep their own columns. Read the value in the row that `map()` receives, under its key. The `$visible` and `$hidden` of the model still apply to that key, but the casts and accessors that the model declares under it no longer apply to the value: change the value in `map()`. An alias of up to 255 characters is returned on every database. Before, PostgreSQL dropped the value of an alias of more than 63 characters.
- `alias` must look like an identifier, `^[A-Za-z_][A-Za-z0-9_]*$`, of at most 255 characters. Another string gives a 422 under `search.aggregates.<i>.alias`, such as `The search.aggregates.0.alias field format is invalid.` Before, an alias with a dot or a space gave a 500.
- Each key of a row names one value. The key of an aggregate, its `alias` or `<relation>_<type>_<field>`, must differ from the name of each field of the resource, `writeOnly()` and `virtual()` fields included, from the name of each relation, from the key of each include of the same search, from the key of each other aggregate of the same search, and from the gates key. Otherwise the aggregate gets a 422 under `search.aggregates.<i>.alias`: `The search.aggregates.0.alias conflicts with a field of this resource.`, `... conflicts with a relation of this resource.`, `... is already used by an include.`, `... is already used by another aggregate.` or `... is reserved.` An aggregate without `alias` gets `The search.aggregates.0.alias field is required, because the key posts_count is already used.` The include it clashes with gets a 422 too, under `search.includes.<i>.alias`.
- `relation` names a relation of the resource or a path of relations, such as `tickets.tags`. The aggregate reads the rows at the end of the path, and counts each of them once, however many rows of the levels before it lead to it. The `perimeterQuery()` of the resource of each level and the global scopes of its model apply: a row that a level leaves out leads to nothing. A `HasOne`, `MorphOne` or `HasOneThrough` level leads to every row that it relates, as in a filter on a relation; its of-many form leads to its one row. The intermediate table of a `HasOneThrough` or `HasManyThrough` relation is not a level. `filters` apply to the rows at the end of the path, with the fields of their resource. A path has at most `rest.limits.depths.includes` relations, counted from the level of the aggregate: a longer path gives a 422 under `search.aggregates.<i>.relation`, `The search.aggregates.0.relation field goes deeper than the limit of 3 include levels.` The user needs `viewAny` on the resource of each relation of the path, checked before validation. The key joins the relations of the path: `<relation>_<relation>_<type>_<field>`, such as `tickets_tags_count`, and a sort can name it. Before, a path gave a 500. For example, the tags of the tickets of each team:

  ```json
  {"search": {"aggregates": [{"relation": "tickets.tags", "type": "count"}]}}
  ```

- An aggregate whose `relation` is a `MorphTo` relation, or a path that ends on one, gives a 422 under `search.aggregates.<i>.relation`: `The search.aggregates.0.relation field cannot be a MorphTo relation.` Before, it gave a 500. With several targets, the user needs `viewAny` on each of them before the 422. A path that goes through a `MorphTo` relation gives `The search.aggregates.0.relation field cannot go through a MorphTo relation.`, or, through a relation with several targets, `The search.aggregates.0.relation field is not a valid or allowed relation for this resource.` Aggregate a relation of the target inside the include instead:

  ```json
  {"search": {"includes": [{"relation": "labelable", "types": {"book": {"aggregates": [{"relation": "labels", "type": "count"}]}}}]}}
  ```

Include aliases:

- An include `alias` is still checked against the name of every field of the resource, `writeOnly()` and `virtual()` fields included. Its messages start with the key instead of `The alias`, for example `The search.includes.0.alias conflicts with a field of this resource.`
- An include `alias` is also checked against the keys of the aggregates of the same search, and against the key of each include without alias, the name of its relation in snake case (`featured_products` for `featuredProducts`): `The search.includes.0.alias is already used by an aggregate.` and `The search.includes.1.alias is already used by another include.` An include without alias whose key is the name of a field of the resource, `writeOnly()` and `virtual()` fields included, a reserved key or the key of an aggregate gets `The search.includes.0.alias field is required, because the key posts is already used.` Before, such an include replaced the field in the row.
- Inside a `MorphTo` include, the type key, `morph.type_key`, is reserved as the gates key is: an include or aggregate `alias` of that name gets `The search.includes.0.aggregates.0.alias is reserved.` `Relation::reservedKeys(): array` returns the keys that a relation adds to each row it loads: the type key for a `MorphTo`, the pivot key for a `BelongsToMany`, `MorphToMany` or `MorphedByMany`, none for the other relations.
- Inside a `BelongsToMany`, `MorphToMany` or `MorphedByMany` include, the pivot key of the relation, `pivot` or the name given to `as()` in snake case, is reserved the same way. Before, an aggregate of that name was replaced by the pivot, and an include of that name gave a 500. A field of the related resource named like it is still replaced by the pivot.
- The classes of `Lomkit\Rest\Rules\Search`, `SearchIncludeAlias` included, and `Lomkit\Rest\Rules\Concerns\ReachesRelations` are removed. A search can no longer be built from rule nodes, and `Search::nested()` is gone. The search payload is read by the package's search parser, see [Validation engine](#validation-engine).

Scramble:

- The search body documents under `sorts` the sortable fields, the fields of the to-one relations (`author.last_name`) and the default key of each aggregate (`comments_count`), the selectable fields under `selects`, the values of `SortDirection`, and under `aggregates` the functions of `AggregateFunction` with the fields that each one takes.

### Filters

A filter reads its field through the field's declaration: the field decides whether it can be filtered, which operators it accepts and how its value is validated.

```php
use Illuminate\Contracts\Database\Eloquent\Builder;

Integer::make('views'),                            // =, !=, >, >=, <, <=, between, not between, in, not in
Text::make('summary')->nullable(),                 // the Text operators, plus is null and is not null
Text::make('title')->filterable(['=', 'like']),    // = and like only
Text::make('slug')->filterable(false),             // refused in filters
Text::make('full_name')
    ->virtual()
    ->filterable(['=', 'like'])
    ->filterUsing(fn (Builder $query, string $operator, mixed $value) => $query
        ->where('first_name', $operator, $value)
        ->orWhere('last_name', $operator, $value)),
```

Fields:

- `filters` accepts the filterable fields only. A `Json` field, a field declared `filterable(false)`, and a `virtual()` or `writeOnly()` field without `filterUsing()` give a 422 under `search.filters.<i>.field`, as an undeclared field does. A field declared `selectable(false)` gives the same 422 unless it is declared `filterable()`, with or without `filterUsing()`. The message changes for both: `The selected search.filters.0.field is invalid.` instead of `The 'field' field is not valid.`
- A dotted path is checked on the resource at its end: `author.first_name` needs a filterable `first_name` field on the resource of the `author` relation. A `pivot` segment is accepted only after a `BelongsToMany`, `MorphToMany` or `MorphedByMany` relation and right before the last segment, which must be a filterable pivot field of that relation: `tags.pivot.weight`. The filters of includes and aggregates are checked on the related resource the same way.
- A field with `filterUsing()` is filtered by its callback instead of its column, also at the end of a relation path: `author.full_name` runs the callback of `full_name` on the query of the authors. The callback receives the Eloquent query, the operator as a string and the value as sent. It can also be an object that implements `Lomkit\Rest\Contracts\Filter`. A `virtual()` or `writeOnly()` field with `filterUsing()` is filterable. The conditions of the callback stay in their own group, so an `orWhere()` in it does not leave the filter.
- The other fields are compared on their column, qualified with the table of the model, or with the pivot table of the relation for a pivot field, morph pivot tables and the table of a pivot class included.

Operators:

- The operators depend on the type of the field:

  | Field | Operators |
  | --- | --- |
  | `ID`, `Enum` | `=`, `!=`, `in`, `not in` |
  | `Text` | `=`, `!=`, `like`, `not like`, `ilike`, `not ilike`, `in`, `not in` |
  | `Number`, `Integer` | `=`, `!=`, `>`, `>=`, `<`, `<=`, `between`, `not between`, `in`, `not in` |
  | `Boolean` | `=`, `!=` |
  | `Date`, `DateTime` | `=`, `!=`, `>`, `>=`, `<`, `<=`, `between`, `not between` |
  | `Json` | none |

- `is null` and `is not null` exist only on `nullable()` fields, which add them to the operators of their type.
- `ilike`, `not ilike`, `between`, `not between`, `is null` and `is not null` are new. The operators are the cases of `Lomkit\Rest\Fields\Filtering\FilterOperator`.
- `filterable([...])` narrows the operators of a field to the listed ones. Each one must be an operator of the field: `Boolean::make('featured')->filterable(['>'])` throws `Lomkit\Rest\Exceptions\InvalidFieldException` when the operators of the field are read.
- An operator that the field does not accept gives a 422 under `search.filters.<i>.operator`: `The selected search.filters.0.operator is invalid.` Before, every field accepted `=`, `!=`, `>`, `>=`, `<`, `<=`, `like`, `not like`, `in` and `not in`.
- A filter without `operator` still compares with `=`. On a field that does not accept `=`, such as `filterable(['like'])`, `operator` is required.

Values:

- The value is validated for the operator and the type of the field, under `search.filters.<i>.value`, or `search.filters.<i>.value.<n>` for an entry of a list:
  - `=`, `!=`, `>`, `>=`, `<` and `<=` need a value of the field's type: a string for `Text`, an integer for `Integer`, a number for `Number`, a boolean for `Boolean`, a date for `Date` and `DateTime`, a value of the enum for `Enum`, and a key of the model's key type for `ID`.
  - `like`, `not like`, `ilike` and `not ilike` need a string of at most `limits.lengths.patterns` characters, see [Validation engine](#validation-engine).
  - `in` and `not in` need a list of at least one value, and `between` and `not between` a list of exactly two values, each of the field's type.
  - `is null` and `is not null` refuse a value.
- `=` and `!=` with `null` give a 422 under `search.filters.<i>.value`. Before, `=` with `null` matched the rows without a value, and `!=` with `null` the rows with one. Use `is null` or `is not null` on a nullable field instead: `{"field": "summary", "operator": "is null"}`.
- A `Date` value is compared as the start of its day, and a `Date` or `DateTime` value is written in the date format of the model before the comparison.

Relations:

- A filter on a relation path (`author.first_name`, `author.tags.name`) matches the rows that have a related row matching the filter, as before, and applies the `perimeterQuery()` of the resource of each relation of the path, each in its own group: `author.tags.name` matches a post only through an author and a tag that the `perimeterQuery()` of their resources keep. Before, only the resource at the end of the path applied it, so a filter could match through a related row that `perimeterQuery()` hid. Each `perimeterQuery()` runs in its own group: an `orWhere()` in it no longer makes the filter match related rows outside the filter.
- `type` and `nested` work the same on relation paths and on fields: a filter with the `or` type is combined with the previous one by `or`, and the filters of a `nested` group stay inside that group.
- A filter through a `MorphTo` relation, such as `labelable.title`, reads the field on the resource that the relation declares, and only matches the rows whose morph type is the model of that resource and that its `perimeterQuery()` keeps. Rows of another type never match, and a stored type that is not a model class no longer gives an error. The relation can be anywhere in the path, as in `labels.labelable.title` or `labelable.loan.borrower`, and the `perimeterQuery()` of the resource it declares applies there too, in its own group. With several targets, a dotted filter through the relation gives a 422 (`The selected search.filters.0.field is invalid.`): use a relation group with `types`.
- An include or an aggregate whose `relation` ends with a `pivot` segment, such as `tags.pivot`, gives a 422 under its `relation` key. Before, it passed validation and failed when the query ran. `$resource->definition()->relation('tags.pivot')` returns `null`.

Relation groups:

- A filter that holds `relation` is a relation group. `{"relation": "posts", "exists": false, "nested": [{"field": "status", "value": "draft"}]}` keeps the records that have no post in draft. Its keys are `relation`, `exists`, `type` and `nested`, and `types` on a `MorphTo`.
- With `exists` `true`, the default, the group keeps the records that have at least one related record that matches every filter of `nested`. With `exists` `false`, it keeps the records that have no such related record. Without `nested`, or with an empty list, any related record counts.
- A group applies all its filters to the same related record. `{"relation": "posts", "nested": [{"field": "status", "value": "published"}, {"field": "featured", "value": true}]}` keeps the records that have one post that is both published and featured. Two dotted filters, `posts.status` and `posts.featured`, can match two different posts. A dotted filter keeps its meaning: `{"field": "posts.status", "operator": "!=", "value": "draft"}` keeps the records that have a post that is not a draft.
- The filters of `nested` take the fields of the related resource, with their operators and values, `type`, groups, relation paths and relation groups. The `perimeterQuery()` of the related resource applies to the related records, in its own group. `type` combines the group with the previous filter, as for any filter.
- `relation` names one relation of the resource. Another name, or a dotted path such as `posts.comments`, gives "The selected search.filters.0.relation is invalid.". A `null` `relation` gives "The search.filters.0.relation field is required.", and a value that is not a string also gives "The search.filters.0.relation field must be a string.". To filter on a relation of the related resource, use a relation path in `nested`, such as `{"relation": "posts", "nested": [{"field": "comments.body", "value": "Thanks"}]}`. A relation group in `nested` holds filters when `rest.limits.depths.filters` is 2 or more.
- `exists` is `true` or `false`. Another value, `1`, `0` and `null` included, gives "The search.filters.0.exists field must be true or false.".
- `field`, `operator`, `value` or another key next to `relation` gives a 422 on that key: "The search.filters.0.field field is prohibited.". `exists` on a filter without `relation` gives the same 422.
- On a `MorphTo`, the group takes `types`, one list of filters for each type: `{"relation": "labelable", "types": {"book": [{"field": "title", "value": "Dune"}], "shelf": []}}` keeps the rows whose target is a book titled Dune or any shelf. An empty list means any target of that type. Without `types`, any target of a declared type counts. Each type applies its fields and its `perimeterQuery()`, and needs `viewAny`. With several targets, `nested` gives a 422; with one target, `nested` works as before, but not together with `types`. With `exists` `false`, the group keeps the rows of other types, of a type that is not a target and without a type.
- On a `BelongsToMany`, `MorphToMany` or `MorphedByMany` relation, `nested` takes the fields of the related resource. A pivot field there, such as `pivot.position`, gives a 422. Filter it with a path from the resource that declares the relation, such as `tags.pivot.position`.
- The filters of includes and aggregates and the search of an action accept relation groups. A text search run by a search engine does not, see [Search engines](#search-engines).

Text search:

- With `Contains` and `FullText`, `search.filters` follow the rules above next to the text. A text search that runs on a search engine accepts the filters of its `ScoutEngine` declaration, see [Search engines](#search-engines). The filters of includes and aggregates always follow the rules above.

Database differences:

- `like` and `not like` follow the database: they are case-sensitive on PostgreSQL, follow the collation of the column on MySQL (case-insensitive on the default `_ci` collations), and ignore the case of ASCII letters only on SQLite.
- `ilike` and `not ilike` ignore case. PostgreSQL runs `ilike`, and MySQL and SQLite run `like`: on MySQL, `ilike` stays case-sensitive on a column with a binary or case-sensitive collation, and on SQLite it ignores the case of ASCII letters only.

Query builder and custom relations:

- `Builder::filter()` and `applyFilters()` are removed. `FilterGroupClause::of()` applies the filters of a search as one group of clauses (a `FieldConditionClause`, a `RelationConditionClause`, a `RelationGroupClause` or a `MorphGroupClause` per filter), which call `Field::filter()`. A relation group uses `has()` on the Eloquent relation of that name, with `<` when `exists` is `false`. On a `MorphTo`, a `MorphGroupClause` uses one `hasMorph()` on the models of the listed types, inside `whereNot()` when `exists` is `false`, and the rows without a type are kept.
- `Relation::filter()` and the `Lomkit\Rest\Contracts\RelationResource` interface are removed. Remove `implements RelationResource` from your custom relations: an override of `filter()` is no longer called, and a filter through the relation uses `has()` on the Eloquent relation of the same name.
- `Resource::isNestedField()`, the `Lomkit\Rest\Rules\Resource\ResourceFieldOrNested` rule and the `relation_without_pivot()` helper are removed, with no public replacement: filters, includes and aggregates validate their dotted paths themselves.
- The package no longer autoloads a file of global functions.

Scramble:

- The `filters` of the search body document one condition per filterable field, with the operators of that field and the value that each operator takes, see [OpenAPI documentation](#openapi-documentation).

### Writes

A mutation writes each attribute through its field: the field decides whether the operation can write it, which rules validate it and how the model is filled.

```php
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

ID::make('id'),                                    // refused on create and update
ID::make('uuid')->creatable(),                     // a key that clients send on create
Text::make('title')
    ->rules('max:255')
    ->creationRules('required'),
Number::make('rating')->nullable(),                // a number or null
DateTime::make('created_at')->readonly(),          // refused on create and update
Text::make('slug')->updatable(false),              // written on create only
Text::make('api_token')->writeOnly(),              // written, never returned
Text::make('full_name')
    ->virtual()
    ->fillUsing(function (Model $author, mixed $value): void {
        $author->forceFill([
            'first_name' => Str::before((string) $value, ' '),
            'last_name' => Str::after((string) $value, ' '),
        ]);
    }),
```

Attributes:

- `attributes` accepts only the fields that the operation can write: the creatable fields on `create`, and the updatable fields on `update`, `sync` and `toggle`. A field is creatable and updatable by default. `ID` fields and fields declared `readonly()` are refused: declare `->creatable()` on a key that clients send, such as a UUID. `creatable(false)` and `updatable(false)` refuse a field on one operation. A `writeOnly()` field is accepted.
- A `virtual()` field is refused unless it declares `fillUsing()`. The callback receives the model and the value and fills the model. It runs after the other fields of the entry are filled.
- An attribute that the operation cannot write gives a 422 under the `attributes` key of the entry, as an undeclared attribute did. The message changes for both: `The created_at key of the mutate.0.attributes field is not writable on create.` On `sync` and `toggle`, it ends with `on update.` Precognition responses change the same way.
- The model is filled with the writable fields of the operation only, field by field, after the `mutating()` hook. An entry that writes no attribute, the target of an `attach`, a `detach`, a `sync` or a `toggle` without attributes, is not saved and runs no `mutating()` or `mutated()` hook: only its link is written (the pivot row, or the foreign key of a child, saved with its model events). Before, the row was saved, its `saving` and `saved` events fired and the hooks of its resource ran.

Rules:

- The rules of each writable field validate its attribute: the base rule of its type, `nullable`, `rules()`, and `creationRules()` on `create` or `updateRules()` on `update`, `sync` and `toggle`. The base rule now applies to mutations: `null` is refused unless the field is `nullable()`, and an array is refused except on a `Json` field. For example, a `Text` field needs a string, an `Integer` field an integer, an `Enum` field a value of its enum, and a `Date` or `DateTime` field a date.
- The resource rules still apply, for rules that compare attributes and for nested keys such as `metadata.source`. Each key gets one list: the field rules, then `createRules()` or `updateRules()`, then `rules()`. Pipe strings are split before the merge, so `'string|min:3'` in `rules()` and `'required'` in `createRules()` give `['required', 'string', 'min:3']`. Before, the resource rules were merged with `array_merge_recursive()`, which left a pipe string unsplit next to another rule of the same key. `rules()`, `createRules()` and `updateRules()` are read once per request, and the entries share the rule objects they return, see "Custom rules" in [Validation engine](#validation-engine).
- A rule that names another attribute, such as `required_if:featured,true` or `after_or_equal:published_on`, compares with that attribute in the same entry, `mutate.0.attributes.featured`, when the resource declares a field of that name. Before, it read the key of that name at the root of the payload. A `Rule::when()` condition receives the attributes of its entry. A rule that depends on the record of the entry, such as `Rule::unique()->ignore()`, is built in `Contextual::rule()`, see "Custom rules" in [Validation engine](#validation-engine).

Rows that a write reaches:

- A resource has one perimeter, `perimeterQuery()`: the rows that exist for the client. Searches and actions read through it, at every level of a relation path, except the rows that a search engine returns for a text search (see [Search engines](#search-engines)), and so do the keys of `mutate`, at the root and under relations, each in the resource of its row, and the keys of `destroy`, `restore` and `forceDestroy`. A key outside it gives a 422, with the message of a key that does not exist. Before, each write endpoint read its keys through its own hook, `mutateQuery()`, `destroyQuery()`, `restoreQuery()` or `forceDeleteQuery()`, and a client could change or delete a row that its searches did not return.
- The write hooks are removed. Move the condition of each override:
  - to the policy when the client may see the row but not change it, such as a locked row or a row of another owner: `update`, `delete`, `restore` or `forceDelete` refuses the row with a 403, see [Authorization](#authorization);
  - to `perimeterQuery()` when the row must not exist for the client on that endpoint: `perimeterQuery()` reads the request of the endpoint with `$this->request()`, so branch on it. The row then gives a 422 on that endpoint only. For example, to let `mutate` reach rows that the other endpoints leave out:

  ```php
  public function perimeterQuery(Builder $query): Builder
  {
      if ($this->request()->endpoint() === Endpoint::Mutate) {
          return $query;
      }

      return $query->where('published', true);
  }
  ```

  A `perimeterQuery()` that reads data of a search request checks `$this->request()->endpoint()` before it reads them.
- A created row is not checked against `perimeterQuery()`: it is written, and its record in `records` is `null` when `perimeterQuery()` leaves it out.

Nested writes:

- The keys that the parent relation sets win over the attributes of the same name. On `HasOne`, `HasOneOfMany`, `HasMany`, `MorphOne`, `MorphOneOfMany` and `MorphMany`, the entry gets the key of the parent, and on the morph relations its morph type, also when the entry sends its own `BelongsTo` or `MorphTo` relation to another parent. On `BelongsTo` and `MorphTo`, the foreign key and the morph type that the relation sets win over the attributes of the entry. They are read after the `mutating()` hook, so a key that the hook sets on such a relation, for example with `associate()`, is the one written. Before, an attribute such as `category_id` replaced them.
- The rows that a mutation names are read once, by the key check, during validation. Before, each entry read its row again when it was written, outside the key check:
  - the hooks and the policies see a row as validation read it. Each entry that names the row writes the same model instance, in the order of the entries, so it sees what the entries before it saved, and `wasChanged()` answers for its own write. A relation already loaded on that instance stays loaded;
  - an entry nested under the relations of an entry that names the same row writes that same instance: the outer entry then compares its attributes with what the nested entry saved;
  - a `perimeterQuery()` that takes in rows that the global scopes of the model leave out, such as with `withTrashed()`, also applies to the write: the row is written. Before, its key was accepted and the write answered 404;
  - a row that another request deletes between the validation and the write is not written, and the mutation answers 200. Before, it answered 404;
  - a `with()` in `perimeterQuery()` loads its relations once for the rows of the key check.

  `sync` and `toggle` read the attached rows of each parent row once, see "Row checks of `mutate`" in [Authorization](#authorization).
- An `update` or a `detach` under a `HasOne`, `HasOneOfMany`, `HasMany`, `MorphOne`, `MorphOneOfMany` or `MorphMany` relation reaches only a row of the parent: a row whose foreign key, and morph type on the morph relations, names the parent. Another row gives "The selected mutate.0.relations.posts.0.key is invalid.", as a key that does not exist, and so does any row under a created parent. `attach` still takes a row of another parent and moves it. Before, `update` moved a row of another parent to the parent, and `detach` cleared the foreign key of a row of another parent.
- `attach` and `create` under a `HasOne` or `MorphOne` relation give a 422 when the parent already has a related row: "The mutate.0.relations.loan field is prohibited: this relation already holds a record. Detach it first." Every row that the foreign key links to the parent counts, also one that the `perimeterQuery()` of the related resource leaves out. Detach the current row in an entry written before, for example:

  ```json
  {"mutate": [
    {"operation": "update", "key": 2, "relations": {"loan": {"operation": "detach", "key": 5}}},
    {"operation": "update", "key": 2, "relations": {"loan": {"operation": "attach", "key": 7}}}
  ]}
  ```

  `HasOneOfMany` and `MorphOneOfMany` relations take another row as before. Before, the parent ended with two related rows.
- A `detach` under a `BelongsTo` or `MorphTo` relation names the current owner of the row, and on a `MorphTo` its `type`: another row gives "The selected mutate.0.relations.author.key is invalid.", and so does a `detach` under a created row. Before, any row cleared the association.
- These checks run during validation, before any policy, any event, any hook and any write, on the rows that the key check reads. They follow the entries in the order in which they are written: an entry sees what the entries before it attach, detach and create. A foreign key that an entry writes as an attribute is not followed. The current row of a `HasOne` or `MorphOne` relation is read with one query per relation and request; the other checks read no row. When an entry of the request cannot be read, for example with an unknown `operation` or a value of the wrong type, these checks give no error.
- A `detach` under a `MorphMany` relation clears the morph type of the row, as under `MorphOne` and `MorphOneOfMany`. Before, it cleared the foreign key only.
- `MorphOne`, `MorphOneOfMany` and `MorphMany` write the morph type with `getMorphClass()`: with a morph map, they write the alias instead of the class name, as `MorphTo` does.
- When a resource keeps the morph type column of a `MorphTo` writable, the column accepts the morph types of its targets only: `The selected mutate.0.attributes.labelable_type is invalid.`. Declare the morph columns `readonly()`.

Response:

- The mutate endpoint answers `operations` instead of `created` and `updated`: one entry for each entry of `mutate`, at the same index, and one for each entry of its `relations`. For example, a book created with a new shelf and two attached shelves, then a book updated:

  ```json
  {"operations": [
    {"operation": "create", "key": 3, "relations": {"shelves": [{"operation": "create", "key": 9}, {"operation": "attach", "key": [1, 2]}]}},
    {"operation": "update", "key": 2}
  ]}
  ```

  Before, the response was `{"created": [3], "updated": [2]}`: the keys of the root entries, grouped by operation, without their relations.
- `operation` is the operation of the entry. `key` is the key of the row that a `create` wrote. On another operation, it is the `key` that the entry sent, typed like the `ID` field of its resource, so `"2"` answers `2` on an integer key. An entry with a list of keys answers once, with the list.
- An entry that sends `relations` answers `relations`, under the names that it sent, in the same order: an object for a to-one relation, a list for a to-many relation, with one entry for each entry that the relation sent. An entry that sends no relation answers no `relations` key.
- An entry of a `MorphTo` relation also answers `type`, the morph type of its row: the morph-map alias of the model, or its class name without an alias, as `@type` in a search: `{"operation": "attach", "type": "book", "key": 2}`.
- `sync` and `toggle` answer the keys that they sent. The rows that they detach or attach besides these keys are not listed.
- An entry under an entry with a list of keys is written once for each of these keys, and answers once: a `create` there answers the list of the keys that it created, in the order of the keys above.
- A `BelongsTo` or `MorphTo` entry is written before the row that holds it, and answers under that row, as sent.
- Without `response` (below), the response holds `operations` only. When the mutation fails, for example on a denied row, the transaction is rolled back and the response is the error alone.
- `Lomkit\Rest\Http\MutateResponse` runs the mutation in one transaction, on the connection of the model of the endpoint's resource (before, on the default connection; rows of models on another connection are written outside it), and renders `operations`, and `records` when the request sends `response`. `mutatingAll()` and `mutatedAll()` run inside the transaction, see [Events](#events).
- `destroy`, `restore` and `forceDestroy` write their rows in one database transaction, on the connection of the model of the resource. For each row, in the order of `resources`, the hook before the write (`destroying()`, `restoring()` or `forceDestroying()`), `performDelete()`, `performRestore()` or `performForceDelete()`, and the hook after the write run inside it. `destroyingAll()` runs inside it before the first row and `destroyedAll()` after the last row; the same holds for `restoringAll()`, `restoredAll()`, `forceDestroyingAll()` and `forceDestroyedAll()`, and a throw in one of them rolls the rows back. A failure on any row, in a hook, in a `perform` method or in the database, rolls back the rows written before it, and the request answers with the error. An observer or a listener that runs after commit runs only when the transaction commits. Before, each row was written on its own, and the rows written before a failure stayed written. A `perform` method that writes on another connection writes outside the transaction.
- `Lomkit\Rest\Http\RowBatch::write()` takes three closures: `write(Closure $before, Closure $write, Closure $after)`. The first receives the rows before the first row is written, the last after the last row, both inside the transaction.
- `transactions.attempts` in `config/rest.php` (`1` by default, `REST_TRANSACTION_ATTEMPTS`) runs a transaction of the package again when the database reports a deadlock or a lock timeout: the transaction of a mutation, of `destroy`, `restore` and `forceDestroy`, and of each chunk or whole run of an action, in the request or in the worker. Each attempt rolls the previous one back, gives the rows read before the transaction their attributes as read, and runs everything inside the transaction again, at once: the row checks, the hooks, the events and `handle()`. Send mails, HTTP calls and jobs after the commit, with `afterCommit()`, `ShouldDispatchAfterCommit` or a listener of the Laravel events of the package (see [Events](#events)), so that they run once. Laravel runs only the outermost transaction again: inside a transaction of your application, a deadlock throws `Illuminate\Database\DeadlockException` at once.
- The package collects the entries of the response in a `Lomkit\Rest\Mutations\OperationLog` that each mutation creates: `RestContext::operations()` and `OperationLog::start()` are removed, and `OperationLog::begin()` and `OperationEntry::of()` take a `Lomkit\Rest\Mutations\MutationNode`.

Response block:

- A mutation returns the rows that it wrote when it sends `response` next to `mutate`, with `selects`, `aggregates`, `includes` and `gates`, as in a search. For example:

  ```json
  {"mutate": [
    {"operation": "update", "key": 2, "attributes": {"title": "Dune Messiah"}},
    {"operation": "create", "attributes": {"title": "Ivanhoe"}}
  ], "response": {"selects": [{"field": "title"}], "includes": [{"relation": "labels", "selects": [{"field": "text"}]}], "gates": ["update"]}}
  ```

  The response then holds `records` after `operations`: one record for each entry of `mutate`, at the same index, rendered as a search renders its rows:

  ```json
  {"operations": [{"operation": "update", "key": 2}, {"operation": "create", "key": 3}], "records": [
    {"title": "Dune Messiah", "gates": {"authorized_to_update": {"allowed": true, "message": null}}, "labels": [{"text": "Classic"}]},
    {"title": "Ivanhoe", "gates": {"authorized_to_update": {"allowed": true, "message": null}}, "labels": []}
  ]}
  ```

- The records are read after the transaction, through the search of the resource: its `perimeterQuery()` applies, and a row that it leaves out, or that is no longer in the table, gives `null`. Two entries on the same row give the same record. The entries under `relations` give no record: request their rows through `includes`.
- `selects`, `aggregates`, `includes` and `gates` follow the rules of a search, at every level, and an include keeps its `limit` for each record. Another key of a search gives a 422 under that key, for example `The response.filters field is prohibited.`, and so does `create` in `gates`, which has no row to answer on: `The response.gates.0 field is prohibited.`. A `response` that is not an object gives `The response field must be an array.`. Before, `response` was ignored.
- The records are read from the database: the text search of the resource does not apply to them.
- `response` needs `viewAny` on the resource, and on each resource that its `includes` and `aggregates` reach. These checks run with the other checks of the request, before validation and before any write: a denied one answers `403` and nothing is written.
- The package drops its decisions on each row that it writes, so the gates of a record answer for the row as the mutation left it.
- The records go through the `map()` of the resource's `Response`, as the rows of a search. They are read and rendered after the commit, after `mutatedAll()`: one query for the rows of every entry, plus one query for each include.
- `MutateRequest::responseBlock()` returns the block as a `Lomkit\Rest\Search\SearchNode` once the request passed validation, or `null` without `response`. `Lomkit\Rest\Http\MutateResponse::$records` holds the rendered records, or `null`.

Custom relations and query builders:

- `Lomkit\Rest\Fields\MutationOperation` lists the operations: `create`, `update`, `attach`, `detach`, `sync` and `toggle`. `Relation::mutationOperations()` returns a list of its cases instead of strings, for example `[MutationOperation::Create, MutationOperation::Attach]`.
- A mutation is written by the package, not by a query builder: `mutate()`, `applyMutation()`, `mutations()`, `mutateModel()` and `mutationAbility()` are removed with `Lomkit\Rest\Query\Builder`, and so is the `Lomkit\Rest\Query\Traits\PerformMutation` trait. To change what a write does, use the `mutating()` and `mutated()` hooks of the resource. The writes keep their order; their policy checks run before them.
- Relations do not write. `beforeMutating()`, `afterMutating()` and `ownedAttributes()` are removed from `Relation`, from every relation class and from the `Lomkit\Rest\Contracts\OwnerRelation` contract, and the `Lomkit\Rest\Relations\Traits\Mutates` and `Lomkit\Rest\Concerns\Relations\PerformsRelationOperations` traits are removed, with the `create()`, `update()`, `attach()`, `detach()`, `toggle()` and `sync()` methods of `BelongsToMany`, `MorphToMany` and `MorphedByMany`. The package writes a relation by the class of its Eloquent relation: a `BelongsTo` or `MorphTo` sets the keys of the row before it is saved; a `HasOne`, `HasMany`, `MorphOne` or `MorphMany` (one of many included) sets the key and morph type of the parent on its rows; a `BelongsToMany` or `MorphToMany` writes the pivot table. A relation whose Eloquent relation is of another class, a through relation included, is not written. An override of the removed methods in a custom relation is no longer called: move its work to the `mutating()` and `mutated()` hooks of the resource. `mutationOperations()` stays on `Relation`.
- The relation classes follow the Eloquent relations they declare: `MorphTo` extends `BelongsTo`, `MorphToMany` extends `BelongsToMany`, `MorphedByMany` extends `MorphToMany`, `HasOneOfMany` extends `HasOne` and `MorphOneOfMany` extends `MorphOne`. `HasOne`, `HasMany`, `MorphOne` and `MorphMany` extend the new abstract `Lomkit\Rest\Relations\HasOneOrMany`, and `HasOneThrough` and `HasManyThrough` extend the new abstract `Lomkit\Rest\Relations\HasOneOrManyThrough`, which refuses their entries in `mutate`. Declarations do not change, and a class that extends one of these relations keeps its behaviour. `BelongsTo` implements `Lomkit\Rest\Contracts\OwnerRelation`, and `BelongsToMany` implements `Lomkit\Rest\Contracts\PivotRelation` with the pivot fields, several entries and every `MutationOperation`; their subclasses inherit them. A check `instanceof BelongsTo` now also holds for a `MorphTo`, `instanceof BelongsToMany` for a `MorphToMany` or `MorphedByMany`, `instanceof HasOne` for a `HasOneOfMany` and `instanceof MorphOne` for a `MorphOneOfMany`: test the subclass first, or compare the class. The package still writes a relation by the class of its Eloquent relation, as above. `Lomkit\Rest\Relations\MorphRelation` is removed: a class that extends it extends the class of its Eloquent relation (`MorphOne`, `MorphMany`, `MorphTo`, `MorphToMany` or `MorphedByMany`) or `Relation`, and a check `instanceof MorphRelation` uses `Lomkit\Rest\Contracts\PolymorphicRelation` for a `MorphTo`, or the Eloquent relation (`getMorphType()`, `getMorphClass()`) for the morph columns.
- `Relation::abilities(?string $attach = null, ?string $detach = null)` declares the abilities of the links of a relation. `linkAbility(bool $detach)` reads them, and `parentResource()` returns the resource that declares the relation, whose policy checks the link.
- `Lomkit\Rest\Mutations\MutationNode` gains `$path`, the payload path of its entry, and `RowSet` gains `peek()`, which returns a row that validation loaded without handing it to a write. `MutationParser::of()` takes a `Lomkit\Rest\Mutations\LinkReplay` as its last argument; an override of `MutateRequest::rules()` that builds the parser passes it. A relation is checked by the class of its Eloquent relation, as it is written.
- `Relation::fromResource(Resource $fromResource)` declares `static` as its return type; an override declares it too.

Scramble:

- The `attributes` of the mutate body list the writable fields only, with their field rules and the resource rules for `create` and `update`. The description of `attributes` names the fields writable on each operation.

### Pivot fields

`BelongsToMany`, `MorphToMany` and `MorphedByMany` declare their pivot columns with typed fields. Each pivot field decides which operations write its column, validates the value, and decides whether the value is returned.

```php
use Lomkit\Rest\Fields\DateTime;
use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Relations\BelongsToMany;

BelongsToMany::make('tags', TagResource::class)->pivotFields([
    Integer::make('weight')
        ->nullable()
        ->rules('min:0'),
    Text::make('added_by')->updatable(false),     // written on create and attach only
    Text::make('note')->writeOnly(),              // written, never returned
    DateTime::make('created_at')->readonly(),     // returned, never written
]),
```

Declaration:

- `withPivotFields()` and `withPivotRules()` are removed. Declare `pivotFields([...])` with `Field` objects instead of column names, and move each rule of `withPivotRules()` to the field of its key: `->withPivotFields(['number'])->withPivotRules(['number' => 'numeric'])` becomes `->pivotFields([Number::make('number')])`. A string, or a name declared twice, throws `Lomkit\Rest\Exceptions\InvalidFieldException`.
- A pivot field is a column of the pivot table, and a pivot value is written to its column as sent, encoded first by a `Json` pivot field as described under [Typed fields](#typed-fields). A pivot field declared `virtual()`, or with `fillUsing()` or `resolveUsing()`, throws `Lomkit\Rest\Exceptions\InvalidFieldException` when `pivotFields()` is called.
- `getPivotFields()` returns a `Lomkit\Rest\Fields\FieldSet` instead of an array of names. Call `->names()` to get the old list. `getPivotRules()` and the `$pivotFields` and `$pivotRules` properties are removed.
- The pivot fields are bound to the pivot class of the Eloquent relation: the class given to `using()`, or Laravel's `Pivot`. An `Enum` pivot field reads its enum class from the casts of that class.
- A custom relation that carries pivot fields implements `Lomkit\Rest\Contracts\PivotRelation`, which declares `getPivotFields(): FieldSet` only. The `Lomkit\Rest\Concerns\Relations\HasPivotFields` trait implements it. The package checks this interface instead of the trait.
- `BelongsToMany`, `MorphToMany` and `MorphedByMany` no longer override `rules()`, and `Relation::rules()` no longer returns the pivot rules: the mutate parser validates the `pivot` of each entry of a `PivotRelation`. `Relation::rules()` returns `[]` by default, and the rules it returns apply to the key of the relation in an entry, such as `mutate.0.relations.tags`.

Writes:

- `pivot` accepts only the pivot fields that the operation of its entry can write. `create` and `attach` add a pivot row, so they accept the creatable pivot fields. `update`, `sync` and `toggle` accept the updatable pivot fields. `detach` still refuses `pivot`. `Lomkit\Rest\Fields\MutationOperation::pivotWriteOperation()` maps the operations. A pivot field declared `readonly()` is refused on every operation, and a `writeOnly()` pivot field is accepted.
- A pivot key that the operation cannot write gives a 422 under the `pivot` key of the entry, as an undeclared pivot key does, with a new message for both: `The created_at key of the mutate.0.relations.tags.0.pivot field is not writable on create.` On `update`, `sync` and `toggle`, it ends with `on update.` A `pivot` that is not an object gives `The mutate.0.relations.tags.0.pivot field must be an array.`
- The rules of each writable pivot field validate its value under `mutate.0.relations.tags.0.pivot.weight`: the base rule of its type, `nullable`, `rules()`, and `creationRules()` on `create` and `attach` or `updateRules()` on `update`, `sync` and `toggle`. `null` is refused unless the field is `nullable()`. The rules run when `pivot` is missing too, so a pivot field with `creationRules('required')` is required on `attach`. Each entry is validated with its own operation.
- A rule that names another pivot field, such as `required_with:note`, compares with that key in the pivot of the same entry, and a `Rule::when()` condition receives the pivot values of its entry.
- The relation writes the writable pivot fields of the operation only, also when it is called without validation.
- `update`, `sync` and `toggle` validate the pivot with the updatable fields and `updateRules()`, also when they add a pivot row: validation runs before the database is read, so it cannot know whether the row exists. A `creationRules('required')` pivot field is not required then. Use `attach` to add a row with the creation rules.

Reads:

- The `pivot` of an included row holds the selectable pivot fields only. A pivot field declared `writeOnly()` or `selectable(false)` is not returned.
- Filters on `pivot` keys, such as `tags.pivot.weight`, accept the filterable pivot fields only, with the operators and values of their type, as described under Filters. A pivot field declared `writeOnly()`, `filterable(false)`, or `selectable(false)` without `filterable()`, gives a 422 under `search.filters.<i>.field`.

Scramble:

- The relations are read from the relation objects that `relations()` returns. A field declared in `pivotFields()` is no longer documented as a relation. A custom relation is documented with its class name, the operations of its `mutationOperations()`, as a list when it has many entries, and with its pivot when it implements `PivotRelation`.
- The `pivot` of a relation entry lists the writable pivot fields with their rules. Its description names the fields writable on each operation: `create` and `attach`, then `update`, `sync` and `toggle`.

### Details

The details endpoint, `GET /{resource}`, describes the resource with typed entries. Its keys are `actions`, `metrics`, `instructions`, `fields`, `limits`, `scopes`, `relations`, `rules`, `text`, `gates` and `authorizations`. An excerpt:

```json
{"data": {
  "fields": [
    {"name": "title", "label": "Title", "type": "text", "nullable": false, "select": true, "sort": true, "filter": {"operators": ["=", "!=", "like", "not like", "ilike", "not ilike", "in", "not in"]}, "create": true, "update": true, "rules": {"all": ["string", "max:120"], "create": ["required"], "update": ["filled"]}, "meta": {}},
    {"name": "medium", "label": null, "type": "enum", "nullable": false, "select": true, "sort": true, "filter": {"operators": ["=", "!=", "in", "not in"]}, "create": true, "update": true, "rules": {"all": ["enum"], "create": [], "update": []}, "meta": {}, "values": ["oil", "ink", "watercolor"]}],
  "relations": [
    {"resource": "App\\Rest\\Resources\\ExhibitionResource", "relation": "exhibitions",
     "constraints": {"required_on_creation": false, "prohibited_on_creation": false, "required_on_update": false, "prohibited_on_update": false}, "name": "BelongsToMany",
     "pivot_fields": [
       {"name": "wall", "label": null, "type": "text", "nullable": false, "select": true, "sort": false, "filter": {"operators": ["=", "!=", "like", "not like", "ilike", "not ilike", "in", "not in"]}, "create": true, "update": false, "rules": {"all": ["string"], "create": [], "update": []}, "meta": {}}]},
    {"resource": "App\\Rest\\Resources\\CollectorResource", "relation": "owner",
     "constraints": {"required_on_creation": false, "prohibited_on_creation": false, "required_on_update": false, "prohibited_on_update": false}, "name": "MorphTo",
     "types": {"App\\Models\\Collector": "App\\Rest\\Resources\\CollectorResource"},
     "morph": {"type_column": "owner_type", "key_column": "owner_id"}}],
  "rules": {"all": {}, "create": {"price": ["required", "min:0"]}, "update": {}},
  "text": {"fields": ["title"], "modes": ["words", "phrase"], "relevance": false, "trashed": false, "max_length": 255, "engine": null},
  "authorizations": {"viewAny": {"allowed": true, "message": null}, "create": {"allowed": false, "message": "Only curators add artworks."}}
}}
```

Fields:

- `fields` is a list of entries instead of a list of names. Read the names with `fields[].name`. The entries keep the declared order.
- `label` follows `name`. It is the field's `label()`, or `null` without one. Pivot field entries and the input entries of actions, instructions and scopes have it too, and Scramble documents it.
- `select` is `true` when `search.selects` accepts the field and the response returns it.
- `sort` is `true` when `search.sorts` accepts the field.
- `filter.operators` lists the operators that `search.filters` accept on the field. It is `[]` when the field is not filterable.
- `create` and `update` are `true` when the field is writable on that operation.
- `rules` holds strings. `all` is the type rule, `nullable` and `rules()`. `create` and `update` are `creationRules()` and `updateRules()`.
- `Enum` entries add `values`, the backing values of the enum.

Rules:

- Rules are described as strings everywhere in the details. Pipe strings are split, `Stringable` rules are cast to string, other rule objects are named by their snake class name (`enum`, `password`), and closures and anonymous rule objects are left out.
- The top-level `rules` holds `rules()`, `createRules()` and `updateRules()` under `all`, `create` and `update`. Each one maps an attribute to its list of rules. Before, it held the raw values, and a rule object gave `{}`.

Inputs:

- `scopes` is a list of `{name, fields, meta}` instead of a list of names.
- The `fields` of actions, instructions and scopes are input entries `{name, label, type, nullable, default, rules, meta}`, with `meta` as an object. `Enum` entries add `values`.

Relations:

- `resource` is the class name of the related resource.
- `BelongsToMany`, `MorphToMany`, `MorphedByMany` and the relations that implement `PivotRelation` add `pivot_fields`, one entry per pivot field. The list is `[]` without `pivotFields()`. Other relations have no `pivot_fields` key.
- In a pivot field entry, `create` means written by `create` and `attach`, and `update` means written by `update`, `sync` and `toggle`. `select` means returned in the `pivot` of an included row. `filter.operators` lists the operators of the `<relation>.pivot.<field>` filters. `sort` is always `false`.
- When the model method of the relation does not return a many-to-many relation, the pivot field entries have `select` set to `false` and `filter.operators` set to `[]`.
- `MorphTo` entries add `types` and `morph`. `types` lists every target. A `MorphTo` with several targets has no `resource` key. `types` maps each morph type to its resource class. The morph type is the morph-map alias of the model, or its class without an alias. Read the targets of a `MorphTo` from `types`. `morph` holds the `type_column` and the `key_column` of the relation.
- Detect `pivot_fields`, `types` and `morph` by their presence, not by `name`. `name` is the short class name of the relation or its `$name`, so a subclass changes it.
- `actions`, `instructions` and `relations` are lists, also when a resource returns keyed arrays.

Text search:

- `text` is a new key. It is `null` when `text()` returns `null`. Otherwise it describes the text search of the resource:
  - `fields`: the searchable fields. It is `[]` on `ScoutEngine`, because the index decides what the text matches.
  - `modes`: the accepted values of `search.text.mode`.
  - `relevance`: whether the `@relevance` sort is accepted with a text.
  - `trashed`: whether `search.trashed` can be sent with a text.
  - `max_length`: the longest accepted text.
  - `engine`: `null` on `Contains` and `FullText`.
- On `ScoutEngine`, `engine` describes the search with a filled text. `filters` lists the `filterable()` attributes with their `name`, `type` and `operators`, and `values` for an `Enum`. The perimeter attributes are not listed. `sorts` lists the `sortable()` attributes. `max_sorts`, `max_page_size` and `window` are the limits of the engine, `null` when it has none. `max_sorts` is `0` when only `@relevance` is accepted.
- With a filled text on `ScoutEngine`, `text.engine` replaces the `sort` and `filter` of the field entries. The rest of the grammar is described under [Search engines](#search-engines).

Gates:

- `gates` is a new key. It lists the abilities that `search.gates` accepts on the resource: `view`, `create`, `update`, `delete`, `restore` and `forceDelete`, then the gates that `gates()` declares, see [Responses](#responses). It is `[]` when `isGatingEnabled()` returns `false`.

Authorizations:

- `authorizations` is a new key. It answers, for the user of the request, each class-level ability that the routes of the resource check: `viewAny`, `create`, the abilities that `abilities()` returns for each endpoint, then those of the actions and the metrics. Each ability is a key, listed once, and its answer holds `allowed` and `message`, as a gate does.
- Each action entry and each metric entry ends with `authorized`: `true` when the user passes every ability that its route checks before it reads the request, `true` when its `abilities()` returns `[]`. A refused action or metric stays in the list with `"authorized": false`, and its route answers `403`. Validation and the row checks of `authorizeEach()` still apply.
- Row abilities are not in the details: list them in the `gates` of a search.
- A resource whose `isAuthorizingEnabled()` returns `false` answers `"allowed": true` to every ability.
- The answers go through the decisions of the request: an ability that `authorizationCache()` declares is read from the cache and written to it, as on the other endpoints.

Behaviour:

- Empty maps are `{}`: the `meta` of every entry, input entries, actions, instructions and scopes included, and the maps of `rules`. Lists stay `[]`.
- Details builds every field entry, the pivot fields and the text search, so it now throws where it listed names:
  - `Lomkit\Rest\Exceptions\InvalidTextSearchException` for a text search declaration error, as on the first search;
  - `Lomkit\Rest\Exceptions\InvalidResourceException` for a `MorphTo` whose model method does not return a `MorphTo` relation of the same name, whose targets are not a list of resources, declare a model twice or a morph type with `*` or a trailing backslash, or for an invalid name in `gates()`;
  - `Lomkit\Rest\Exceptions\InvalidFieldException` for an `Enum` field or pivot field with no enum class and no enum cast;
  - `BadMethodCallException` for a many-to-many relation or a `MorphTo` whose model method is missing.
- Details checks `viewAny` first. Then, after `describing()`, it asks the policy for each ability of `authorizations`; a `create` policy method whose second parameter has no default fails there, as on `mutate`. It lists what the declarations of the resource return for the current request, which can depend on `$this->request()->user()`.
- Clients ignore the keys they do not know. Later releases can add keys.

Scramble:

- Scramble documents the details response of each resource in its own schema, `{Resource}Details`, which lists the names of the resource as enums: the `name` of the fields, the `relation` of the relations, the `name` of the scopes, the `uriKey` of the instructions, actions and metrics, and the gates. It holds the field entries, the relations with `pivot_fields`, `types` and `morph`, the rules, `text`, `gates`, `authorizations`, the input entries of actions, instructions and scopes, and `authorized` on actions and metrics. It also documents the `type` of a `MorphTo` mutate entry and the `types` of an include.

### OpenAPI documentation

The package registers its documentation in the `boot()` method of its service provider, through `Scramble::configure()`, whatever the order in which the providers register. It adds an operation transformer, `Lomkit\Rest\Scramble\RestOperations`, and a document transformer, `Lomkit\Rest\Scramble\RestDocument`, to the default API of Scramble. An API that the application registers with `Scramble::registerApi()` after the package boots starts from the default API and gets them too. For an API registered before, add them:

```php
Scramble::registerApi('v2', ['api_path' => 'api/v2'])
    ->withOperationTransformers(\Lomkit\Rest\Scramble\RestOperations::class)
    ->withDocumentTransformers(\Lomkit\Rest\Scramble\RestDocument::class);
```

The document:

- is the same for every reader, for `scramble:export` and for the document cache of Scramble. The package reads the declarations of each resource as a guest: a request on the path of the operation, without a user. A declaration that adds a field, an action or a metric for some users only is documented as a guest sees it;
- has one operation for each route of an action and of a metric, with the name of the declaration as its summary. The routes `{resource}/actions/{action}` and `{resource}/metrics/{metric}`, which only answer an unknown name, are left out;
- has the summaries `Get {Resource} details`, `Search {Resource}`, `Mutate {Resource}`, `Delete {Resource}`, `Restore {Resource}`, `Force delete {Resource}` and `Batch progress of {Resource} actions`, where `{Resource}` is the resource class name without its `Resource` suffix, also used as the tag;
- holds the responses that the package writes for its operations. Scramble no longer infers the responses of the package routes; it still infers those of the other routes of the application.

Records and read responses:

- `components.schemas` holds the record of each resource that a documented operation reaches, named like the tag (`Post`). It lists the selectable fields with their type, the gates of a row under the gates key, and each relation under its key in the response: the record of its resource or `null` for a to-one relation, a list of records for a to-many relation, with the `pivot` and its selectable pivot fields for a many-to-many relation. A `MorphTo` relation is `oneOf` the `{Resource}WithType` record of each target, which adds the type key (`@type`) with the morph type, with a `discriminator` on that key. The record requires no key, since the `selects` of a search decide which keys a row holds, and the aggregates and include aliases of a search add keys that it does not name.
- `RestGateAnswer` is the answer to one gate: `allowed` and `message`.
- The details answer `{Resource}Details`, see [Details](#details).
- A search answers `{Resource}Paginated`: `current_page`, `data`, `from`, `last_page`, `per_page`, `to`, `total` and `meta`, whose gates key holds the `create` answer. `destroy`, `restore` and `forceDestroy` answer `{Resource}Rows`: `data` and `meta`.
- The body of `destroy`, `restore` and `forceDestroy` requires `resources`: from 1 to `limits.sizes.resources` distinct keys, typed by the `ID` field of the resource. Its request example holds one key: the first value of `examples()` on the `ID` field, else `1` for an integer key, `a` for a string key, `00000000-0000-0000-0000-000000000000` for a UUID key and `00000000000000000000000000` for a ULID key.
- A resource that has the name of another documented resource, such as a second `TagResource` in another namespace, names its records with its namespace: `App.Rest.Admin.Tag`, `App.Rest.Admin.TagPaginated`.
- A `Date` field is documented with `format: date`, and the `date` cast of Laravel renders a date and a time (`2026-07-01T00:00:00.000000Z`). Cast the attribute with `date:Y-m-d` so that the response holds the date alone, as the document says. A `DateTime` field is documented with `format: date-time`, which the `datetime` cast renders. A `Json` field is documented as an object: a value that is a list does not match it.

Search requests:

- The `filters` of a search are `{Resource}Filter`: `oneOf` a condition on a field (`{Resource}FieldFilter`), a condition on related rows (`{Resource}RelationFilter`) and a group of filters (`{Resource}FilterGroup`, with `nested` and `type`). The description of the group gives the nesting depth of `limits.depths.filters`.
- `{Resource}FieldFilter` has one schema per filterable field, `{Resource}Filter{Field}`, with a `discriminator` on `field` (`{Resource}Filter{Relation}_{Field}` for a dotted path). It lists the operators of the field, grouped by the value that they take: the type of the field for `=`, `!=`, `>`, `>=`, `<` and `<=`; a string of at most `limits.lengths.patterns` characters for `like`, `not like`, `ilike` and `not ilike`; a list of 1 to `limits.sizes.values` values for `in` and `not in`; two values for `between` and `not between`; no `value` for `is null` and `is not null`. Without `operator`, the condition is `=`. The filterable fields of each related resource are listed one level deep, such as `author.last_name`, with the operators and the value of their own filter, and the filterable pivot fields as `{relation}.pivot.{field}`, such as `tags.pivot.weight`. Deeper dotted paths are accepted and are not listed; a `MorphTo` relation of several types takes none.
- `{Resource}RelationFilter` has one schema per relation, `{Resource}RelationFilter{Relation}`, with a `discriminator` on `relation`: `exists`, `type`, and `nested`, the filters of the related resource. A `MorphTo` relation adds `types`, the filters of each morph type, and has no `nested` when it has several types.
- The body of a search is `{Resource}Search`: `text` and `trashed` where the resource supports them, `filters`, `gates`, `scopes`, `sorts`, `selects`, `aggregates`, `includes`, `instructions`, `limit` (the page sizes of the resource) and `page` (from 1). A list holds at most its `limits.sizes` entries, and a key that the resource cannot use is left out. `{Resource}Search` lists its keys without refusing others, and excludes `text` and `trashed` with `not` where the resource does not support them. Every object inside it lists its keys and refuses the others (`"additionalProperties": false`).
- `{Resource}Sort` lists the sortable fields, the fields of the to-one relations (`author.last_name`) and the default key of each aggregate that the same search can declare (`comments_count`, `comments_max_rating`); such a key sorts only when the search declares that aggregate. The key of an aggregate with an `alias` is accepted too and is not listed. `{Resource}Select` lists the selectable fields. `gates` lists the abilities of the resource; an include does not list `create`. A resource whose `isGatingEnabled()` returns `false` has no `gates` in its search: the endpoint still accepts the key there, and the rows hold no answer.
- `{Resource}Include` has one schema per relation, `{Resource}Include{Relation}`, with a `discriminator` on `relation`: `relation`, `alias`, and the search of the related resource. An include of a `BelongsTo` relation has no `limit` and no `sorts`, and a `MorphTo` include has `types`, the search of each morph type. The description gives the nesting depth of `limits.depths.includes`. Dotted relation paths are accepted and are not listed: use the `includes` of an include.
- `{Resource}Aggregate` has one schema per relation but `MorphTo`, `{Resource}Aggregate{Relation}`: `count` and `exists` take no `field`, `min` and `max` take the fields that they can order, `avg` and `sum` the `Number` and `Integer` fields.
- `{Resource}Scope` and `{Resource}Instruction` have one schema per scope and per instruction, with a `discriminator` on `name`. A scope lists its `parameters` in order, with their type; the parameters without a default are required. An instruction lists its `fields` as `{"name", "value"}` entries, typed by each field.
- Each search operation has a request example that filters on one field: the first filterable field that declares `examples()`, else the first filterable field, with its first example or a value of its type (`1`, `a`, `true`, `2026-01-01`, the first value of an enum). The example leaves out `operator`, as `=` is the default.

Error responses:

- `components.responses` holds four shared responses: `RestUnauthenticated` (`401`, `{"message"}`), `RestForbidden` (`403`, `{"message"}`, plus the `denials` list when the application registers `DenialsRenderer`, see [Authorization](#authorization)), `RestNotFound` (`404`, `{"message"}`) and `RestValidationFailed` (`422`, `{"message", "errors"}`).
- `401` is listed when the route of the operation runs `auth` or `auth:<guard>` and does not exclude it: a metric declared with `->withoutMiddleware('auth')` on a resource behind `auth` has no `401`. Before, Scramble listed `401` on resources without `auth` too.
- `403` and `404` are listed on every operation of the package, `422` on every operation that reads a body: all but details and batch progress. Before, `422` was listed on details and batch progress, and `403` and `404` nowhere.
- An operation whose route does not run `auth` has `"security": []`, so a security scheme that the application declares for the whole document does not apply to it. The package declares no scheme: declare yours with `$openApi->secure(...)` in a document transformer.
- Two different components under one name, for example a response named `RestNotFound` that the application defines, throw `Lomkit\Rest\Exceptions\InvalidDocumentationException` when the document is generated: "The OpenAPI response component [RestNotFound] of [Lomkit\Rest\Scramble\ErrorResponses] is already defined differently by [the application]. Give one of them another name."

### Framework integration

- `Lomkit\Rest\Concerns\PerformsRestOperations` is removed. Its endpoint methods (`details()`, `search()`, `mutate()`, `operate()`, `metric()`, `batch()`, `destroy()`, `restore()`, `forceDestroy()`) are declared on `Lomkit\Rest\Http\Controllers\Controller`. A controller that used the trait extends `Controller` instead.
- `Lomkit\Rest\Http\Controllers\Controller` no longer extends `Illuminate\Routing\Controller`. The inherited `middleware()`, `getMiddleware()`, `callAction()` and `__call()` methods are removed. Replace the `$this->middleware()` calls in your controller constructors with the `Illuminate\Routing\Controllers\HasMiddleware` interface or with the `#[Middleware]` and `#[Authorize]` attributes:
  - Middleware that a base controller declared for all its children, such as the controller that `rest:base-controller` generates, moves to the static `middleware()` method of `HasMiddleware` on that base controller. Every child controller inherits it. A child controller that needs more middleware overrides `middleware()` and adds its entries to `parent::middleware()`.

    ```php
    use Illuminate\Routing\Controllers\HasMiddleware;
    use Illuminate\Routing\Controllers\Middleware;
    use Lomkit\Rest\Http\Controllers\Controller as RestController;

    abstract class Controller extends RestController implements HasMiddleware
    {
        public static function middleware(): array
        {
            return [
                'auth',
                new Middleware('throttle:60,1', only: ['destroy', 'forceDestroy']),
            ];
        }
    }
    ```

  - Middleware can also use the attributes. Laravel reads them on the controller that the route names and on its parent classes, and adds them after the middleware of `HasMiddleware`.

    ```php
    use Illuminate\Routing\Attributes\Controllers\Authorize;
    use Illuminate\Routing\Attributes\Controllers\Middleware;
    use Lomkit\Rest\Http\Controllers\Controller;

    #[Middleware('throttle:60,1')]
    #[Authorize('manage-posts', only: ['destroy', 'forceDestroy'])]
    class PostsController extends Controller
    {
        public static string $resource = PostResource::class;
    }
    ```

- The `lomkit-rest` container alias is removed. Resolve `Lomkit\Rest\Rest` by its class name, or use the `Lomkit\Rest\Facades\Rest` facade. The container and the facade now return the same instance.
- The package defaults merge into your published `config/rest.php` at every level, see [Configuration](#configuration).
- The keys in `resources` are checked on the connection of the model, through the query of the endpoint, see "Resource keys" in [Validation engine](#validation-engine). Before, `destroy` checked them on the default connection.

### Generators

The classes that the generators write pass Pint with the `laravel` preset and Larastan at level 10:

- They import the classes they use and mark each overriding method with `#[\Override]`. A model or a resource whose short name the file already uses is imported under an alias, for example `use App\Models\Resource as ResourceModel;`. In a resource, the reserved short names are the resource itself, `Resource`, `Rule`, the field classes and the relation classes, so a model named `Rule` or `Text` is imported as `RuleModel` or `TextModel`.
- `rest:action` and `rest:instruction` generate a `fields()` method that returns `[]`. Before, it declared a required `id` field: the instruction refused every request without it, and the action ignored it and ran on every model of the search.
- `rest:action` generates `handle(ActionFields $fields, Collection $models)` and `rest:instruction` `handle(InstructionFields $fields, Builder $query)`, with their `use` lines.
- `rest:base-resource` generates an empty abstract class. Before, it declared the query hooks, which returned `$query` unchanged. Override `perimeterQuery()` in your base resource when its constraints apply to every resource.
- `rest:base-controller` generates an empty abstract class.
- `rest:response` generates a `map()` method with its array types in PHPDoc.

Options:

- `rest:resource`, `rest:controller`, `rest:policy`, `rest:action`, `rest:instruction`, `rest:metric`, `rest:response`, `rest:base-resource` and `rest:base-controller` accept `--force` (`-f`), which replaces an existing class. Without it, an existing class is kept: the command prints `<Type> already exists.` and exits with 0.
- When the command runs interactively, `rest:resource` asks for the model and `rest:controller` for the resource whenever the option is missing, also when the name is given or `-v` is passed. With `--no-interaction` they ask nothing.
- `rest:controller` offers the concrete resources under `app/Rest/Resources`, nested ones included. It reads each file first, and loads only a file that declares a single class, named like the file and its directory, that extends another class and is not abstract: a script or a helper in that directory is never run. `rest:resource --generate` finds the resources of the related models in the same way.

Fields and relations from the model:

- `rest:resource` accepts `--generate` (`-G`): it reads the table of the model through the schema builder and declares a field for each column, in the order of the table, then a relation for each relation method of the model whose related model has a resource. Run it after the migrations: `php artisan rest:resource PostResource --model=Post --generate`.
- When the command runs interactively without `--generate` and the table of the model exists, it asks `Generate the fields and relations from the <table> table?`, and the default answer is yes. When the model or its table does not exist, or the database cannot be reached, it does not ask. With `--no-interaction` and without `--generate`, the resource declares only the key of the model.
- `--generate` stops the command with exit code 1 before any file is written when no model is given or guessed, when the model does not exist, is not an Eloquent model or cannot be instantiated, such as an abstract model, and when its table does not exist.
- An existing resource is kept, as with the other generators: run `--generate --force` to generate it again.
- After the `created successfully` line, the command prints the resource with its counts, for example `PostResource ... 8 fields, 1 relation`, then one `Skipped:` line for each relation that it did not generate and one `Note:` line for each column that needs a review.
- The key field is `ID::make()` with the key name of the model, with or without `--generate`. Before, it was always `ID::make('id')`.

With `--generate`, each column gives one field, through these rules in this order:

1. The key of the model gives `ID`. When the key is neither incrementing nor generated by `HasUuids` or `HasUlids`, the client sends it on create: the field gets `creatable()` and the rules below.
2. A hidden column (`$hidden` or `#[Hidden]`) that the model does not let fill, when the model declares `$fillable`, `$guarded` or their attributes, is left out with a note.
3. A cast that a field type matches gives that type: a backed enum gives `Enum`, the JSON casts (`array`, `json`, `object`, `collection`, their encrypted forms, the `As*` array and collection casts, `AsFluent` and `AsVector`) give `Json`, and the `boolean`, `integer`, `decimal`, `float`, `date` and `datetime` casts give `Boolean`, `Integer`, `Number`, `Date` and `DateTime`.
4. Otherwise the column type gives the field type: `Integer`, `Number`, `Boolean` (also `tinyint(1)`), `Date`, `DateTime`, `Json` for the `json` and `jsonb` types, or `Text` for the string, text, `uuid`, `enum` and `time` types. A `json` or `jsonb` column needs no JSON cast: the `Json` field encodes the value when the model writes the column as it is, as described under [Typed fields](#typed-fields). Another type, such as a binary column, gives `Text` with a note. On SQLite, Laravel creates a `json` column as a text column unless the connection sets `use_native_json`, and a `jsonb` column as a text column unless it sets `use_native_jsonb`. Such a text column gives `Text` without a JSON cast.
5. A nullable column gets `nullable()`.
6. The timestamps of the model (`created_at`, `updated_at`, and `deleted_at` with `SoftDeletes`), the auto-increment and generated columns, the columns that `HasUuids` or `HasUlids` fill, and the foreign key of a generated `BelongsTo` relation get `readonly()`, and nothing else.
7. A hidden column gets `writeOnly()`: clients may write it, and it is never returned. The package does not read `$hidden` at runtime: the generated line declares it.
8. A `Text` or `ID` field whose column reports a length gets `rules('max:<length>')`. MySQL and PostgreSQL report it, SQLite does not.
9. A column that is not nullable and has no default gets `creationRules('required')`, and a column with a unique index of its own gets `Rule::unique(<Model>::class, '<column>')` in its creation rules.

A field with more than one call after `make()` is written one call per line.

With `--generate`, the relations of the model are generated through these rules:

- The command reads the public methods of the model whose declared return type is an Eloquent relation, such as `public function author(): BelongsTo`, and calls them on a new instance. It never calls a method without a declared relation return type, a static method or a method with a required parameter. The relations keep the order of the methods in the model.
- A relation is generated when its related model has a resource: a concrete resource under `app/Rest/Resources` whose `$model` is that model, or the resource being generated, for a relation of the model to itself. When several resources have that model, the resource being generated comes first, then the first class name in alphabetical order. `BelongsTo`, `HasOne`, `HasMany`, `BelongsToMany`, `MorphOne`, `MorphMany`, `MorphToMany`, `HasOneThrough` and `HasManyThrough` give the relation class of the same name, for example `BelongsTo::make('author', UserResource::class)`, and `morphedByMany()` gives `MorphedByMany`. A resource in another namespace is imported.
- A relation whose related model has no resource is not generated, and nothing is exposed for it: the command prints `Skipped: author: no resource for App\Models\User. Generate one first with php artisan rest:resource UserResource --model=User --generate, then run this command again with --force.` Its foreign key stays a writable field.
- A `MorphTo` relation is not generated, since its targets are not in the schema: the command prints `Skipped: commentable: MorphTo relations are not generated, declare it in relations() by hand with its targets, for example MorphTo::make('commentable', [PostResource::class, VideoResource::class]).`, with the resources of the models of the morph map (`Relation::morphMap()`), or `[...]` without one. A relation of another class is not generated either: `Skipped: <name>: <Class> relations are not generated, declare it in relations() by hand.`
- A generated `BelongsTo` relation makes its foreign key `readonly()`, so clients set the key through the relation. When the foreign key column is not nullable and has no default, the relation gets `requiredOnCreation()`.
- A `BelongsToMany`, `MorphToMany` or `MorphedByMany` relation whose definition declares `withPivot()` columns or `withTimestamps()` gets `pivotFields([...])`: one field for each of these columns of the pivot table, typed by the rules above from the column and the casts of the pivot model. The pivot timestamps get `readonly()`, and a pivot field gets no `Rule::unique()`. A note names a pivot column as `<relation>.pivot.<column>`. A declared column that the pivot table does not have gets no field and a note that names the pivot table: `Note: tags.pivot.weight: no such column in the post_tag pivot table, left out.`

Model and resource:

- Without `--model`, `rest:resource` guesses the model from the class name, and uses it when the class exists: `PostResource` gives `App\Models\Post`, and `Admin/PostResource` gives `App\Models\Admin\Post`. Without `--resource`, `rest:controller` guesses the resource the same way: `PostsController` gives `App\Rest\Resources\PostResource`. An empty `--model` or `--resource` counts as a missing one.
- When nothing is given or guessed, the `$model` or `$resource` line is left out and the command warns. Before, it wrote `\Model::class` or `\Resource::class`. A resource without `$model` then throws `InvalidResourceException` on its first request. A controller without `$resource` throws `InvalidControllerException` as soon as `Rest::resource()` registers its routes: the route files, every request and every Artisan command, the generators included, stop until you set `$resource` in the class or remove the route. With cached routes, it throws on its first request instead. Both messages name the class, and `rest:controller` warns that `$resource` must be set before the controller is registered.
- A class that does not exist is written as given, and the command warns.
- A fully qualified class that exists is kept as it is, also outside `App\`. Before, it was prefixed with `App\Models\` or `App\Rest\Resources\`.
- A value that is not a valid class name, such as `Post-1` or `Admin//Post`, stops the command with exit code 1 before any file is written.

Base classes:

- `rest:resource` creates `app/Rest/Resources/Resource.php`, and `rest:controller` creates `app/Rest/Controllers/Controller.php`, when the class it generates was written and the base class does not exist yet. The base class is written after that class and has its own `created successfully` line. Before, it was created first and silently, even when the command then failed. The `--force` of `rest:resource` and `rest:controller` never replaces the base class.
- `rest:resource` refuses the name `Resource`, and `rest:controller` the name `Controller`, in any case and in any namespace, with exit code 1: these names belong to the base classes. When the command runs interactively, it refuses the name before it asks for the model or the resource. Run `rest:base-resource` or `rest:base-controller` to create a base class.
- A class in the namespace of its base class no longer imports it.

Policies:

- `rest:policy` is a new generator. `php artisan rest:policy PostPolicy --model=Post` writes `app/Policies/PostPolicy.php`, a policy that allows every ability: `viewAny()`, `view()`, `create()`, `update()` and `delete()` return `true` for a signed-in user. For a model that uses `SoftDeletes`, `restore()` and `forceDelete()` are added and return `true`. Edit the policy before production.
- The user parameter has the type of the model of the user provider of the default guard (`auth.providers.<provider>.model`), or `App\Models\User` when it is not set. In the policy of the user model itself, the second parameter is named `$model`.
- Without `--model`, the model is guessed from the class name: `PostPolicy` gives `App\Models\Post`, `Admin/InvoicePolicy` gives `App\Models\Admin\Invoice`, and `LearningResourcePolicy` gives `App\Models\LearningResource`. When the command runs interactively, it asks for the model. When no model is given or guessed, or the model does not exist, the command stops with exit code 1 before any file is written.
- When Gate already finds another policy for the model, through `Gate::policy()`, `#[UsePolicy]` or the naming convention, the command prints `The model [App\Models\Post] already has the policy [App\Policies\ContentPolicy].`, writes nothing and exits with 0. With `--force`, it writes the policy and warns that Gate still uses the other one: register the new policy with `Gate::policy()` to use it.

Metrics:

- `rest:metric` is a new generator. `php artisan rest:metric OrdersByStatus --type=partition --group-by=status` writes `app/Rest/Metrics/OrdersByStatus.php`. `--type` is `value`, `trend`, `partition`, `progress`, `table` or `custom`, and the class overrides only what its type needs:
  - `value`: `return $this->count($query);`, without ranges.
  - `trend`: `return $this->countByDays($query);`, with the ranges `7 => '7 days'`, `30 => '30 days'` and `90 => '90 days'`. It counts by `created_at`: on a resource that does not declare that field as `Date` or `DateTime`, pass another field to `countByDays()`.
  - `partition`: `return $this->count($query, 'status');`, with the field of `--group-by`.
  - `progress`: `return $this->count($query, target: 100);`.
  - `table`: `return $this->rows($query, 'channel', ['count' => TableColumn::count()]);`, with the field of `--group-by`.
  - `custom`: a class that extends `Metric`, whose `type()` is the class name in kebab case (`OrderBacklog` gives `order-backlog`) and whose `calculate()` returns `new ValueResult($query->count())`. Return your own `MetricResult` instead.
- Declare the class in `metrics()` of a resource, for example `OrdersByStatus::make()`.
- When the command runs interactively, it asks `Which type of metric?` when `--type` is missing, then `Which field should the metric group by?` for a partition or a table when `--group-by` is missing. With `--no-interaction`, a metric without `--type` is a `value` metric.
- A partition or a table without a field, an unknown type and a field name with characters other than letters, digits and underscores stop the command with exit code 1 before any file is written. The other types ignore `--group-by` and warn.

Published stubs:

- The generators read the stubs that `php artisan stub:publish` copies to `stubs/rest.<name>.stub`, and fall back to the package stubs. Before, they ignored them. The stubs published under 2.x declare untyped properties such as `public static $model`, which are a fatal error in 3.0 (see [Native types](#native-types)): delete your `stubs/rest.*.stub` files, or publish them again with `php artisan stub:publish --force` after you have saved the Laravel stubs that you customised.
- The `stubs/rest/*.stub` files that versions before 2.14 used are not read.
- `rest:metric` reads one stub per type: `rest.metric-value.stub`, `rest.metric-trend.stub`, `rest.metric-partition.stub`, `rest.metric-progress.stub`, `rest.metric-table.stub`, and `rest.metric.stub` for `custom`.
- A published stub keeps the placeholders that the generator fills: `{{ namespace }}` and `{{ class }}` in each stub, `{{ namespacedModel }}`, `{{ model }}`, `{{ imports }}`, `{{ fields }}` and `{{ relations }}` in `rest.resource.stub`, `{{ namespacedResource }}` and `{{ resource }}` in `rest.controller.stub`, `{{ namespacedModel }}`, `{{ namespacedUserModel }}`, `{{ model }}`, `{{ modelVariable }}`, `{{ user }}` and `{{ softDeleteMethods }}` in `rest.policy.stub`, `{{ groupBy }}` in `rest.metric-partition.stub` and `rest.metric-table.stub`, and `{{ type }}` in `rest.metric.stub`. Otherwise the command throws `Lomkit\Rest\Exceptions\InvalidStubException`, which names the stub and the missing placeholder.

### Quick start

`rest:quick-start` exposes the models that you choose, behind authentication, and checks the result before it reports success:

```bash
php artisan rest:quick-start
php artisan rest:quick-start --no-interaction --model=Post --model=Tag --install-api
php artisan rest:quick-start --no-interaction --model=Post --guard=api --install-api
```

- The command asks `Which models should the API expose?` and offers the models of `app/Models`, or of `app` when that directory does not exist, with none selected. With `--no-interaction`, or when no terminal can answer, pass each model with `--model`: without it, the command stops with exit code 1 and writes nothing. Before, it always exposed `App\Models\User`.
- Each model must exist and have a table, and two models whose routes the command adds must not get the same resource name, such as `App\Models\Note` and `App\Models\Archive\Note`, which would both be `notes`. Otherwise the command stops with exit code 1 before any file is written, and names the models.
- For each model, it writes the resource with the fields and relations of the table, as `rest:resource --generate` does, the controller, and a policy that allows every ability, as `rest:policy` does, when Gate finds no policy for the model. It creates the base resource and the base controller when they are missing. The relations between the models of one run are generated. A relation to a model that is not exposed is skipped and reported: `Skipped: Post.author: no resource for App\Models\User. Rerun with --model=User to expose users.`
- The policy is written in `app/Policies`. When Gate finds no policy for a model and would not discover the policy that the command writes, for example for a model outside the namespace of the application, the command stops with exit code 1 before any file is written: register a policy for the model with `Gate::policy()`, then run the command again.
- The routes that the command adds use the `sanctum` guard. Pass `--guard` to use another guard:
  - `sanctum` uses Laravel Sanctum.
  - `api` uses Laravel Passport when `config/auth.php` does not define it, or defines it with the `passport` driver.
  - Any other guard that `config/auth.php` defines, `api` with another driver included, is used as it is.
  - Any other name stops the command with exit code 1 before it writes anything: `The [admin] guard is not defined in config/auth.php.`
- When `routes/api.php` does not exist, the command asks before it runs `php artisan install:api`, which installs Laravel Sanctum, or `php artisan install:api --passport` for Laravel Passport. With another guard, it also runs `install:api` to create `routes/api.php`, and says that it installs Laravel Sanctum. When `routes/api.php` exists, the command adds at least one route and the package of the guard is not installed, it asks `Laravel Sanctum is not installed. Run php artisan install:api now to install it?` or `Laravel Passport is not installed. Run php artisan install:api --passport now to install it?` instead. In this case the install command installs the package, keeps `routes/api.php` and prints `API routes file already exists.`: the command says before the call that the file is kept, and goes on when the install command exits with 0. Laravel Sanctum counts as installed when the `sanctum` guard is defined, and Laravel Passport when the `Laravel\Passport\Passport` class exists. `--install-api` runs the install command of the guard without asking in both cases. With `--no-interaction` or without a terminal, for example in CI, and without `--install-api`, or when you decline, the command stops with exit code 1 and writes nothing. The command no longer edits `bootstrap/app.php`: `install:api` registers `routes/api.php`.
- When an install is needed, `--guard` is not passed and a terminal can answer, the command first asks `Which guard should protect the API routes?`, also with `--install-api`. It offers `sanctum (Laravel Sanctum)`, the default, `api (Laravel Passport)`, then the other guards of `config/auth.php`. Then it asks to run the install command only when the chosen guard needs it, and `--install-api` skips that question only. Pass `--guard` to skip the guard question. Without a terminal or with `--no-interaction`, the guard is `sanctum`. When nothing needs to be installed, the command asks nothing.
- Laravel Passport needs an `api` guard with the `passport` driver in `config/auth.php`, which `install:api --passport` does not add. When this guard is still not defined after the install, the command prints `Define the api guard with the passport driver in config/auth.php.`, and the check in a new process reports the missing guard. The command never edits `config/auth.php`.
- It appends one route per model to `routes/api.php`, behind the `auth` middleware of the guard, `auth:sanctum` by default, and adds the `use` imports of the classes it names:

  ```php
  Rest::resource('posts', PostsController::class)
      ->middleware('auth:sanctum');
  ```

  A controller that `routes/api.php` already names, by its full name, an import, a group import, an alias or an imported namespace, is not added again. Each import goes among the existing ones, group imports included, where the `ordered_imports` rule of the Pint `laravel` preset sorts it, and the existing imports stay as they are. A file without imports gets them after `declare(strict_types=1);`. Before, the route had no middleware.
- When the routes of the application are cached, the command stops with exit code 1 before it asks anything or writes a file, because the routes that it adds would not be served: run `php artisan route:clear`, then run the command again.
- Before it writes anything, the command checks that no route of the application already answers the URIs of the new routes under the prefix of the API routes, or uses their names, such as `posts.destroy` from `Route::apiResource('posts', ...)`. Otherwise it lists these routes and stops with exit code 1: rename or remove them, or leave their models out.
- Then it checks the result in a new PHP process: each controller has routes, each route is behind an authentication middleware whose guards exist, and Gate finds a policy for each model. It prints `checks (fresh process) ... 10 routes authenticated, 2 policies found`. When a check fails, the command lists the problems, the skipped relations and the policy reminder, and stops with exit code 1. The files it wrote stay, and the next run keeps them. Before, the command always exited with 0.
- An existing class is never changed: the command reports it as `kept`. A second run prints `kept` on every line and exits with 0.
- The command never edits the user model. When the user model does not use the `HasApiTokens` trait of the package of the guard, `Laravel\Sanctum\HasApiTokens` or `Laravel\Passport\HasApiTokens`, it prints a warning. It ends with a command that creates a token, with `plainTextToken` for Laravel Sanctum or `accessToken` for Laravel Passport, a `curl` request to the search route that the application registered for the first model, with its API prefix, and, when it wrote policies, a reminder that they allow every ability: edit them before production. With another guard, it prints no warning and no token command: it says `Authenticate with the <guard> guard to call the API.`, for example `Authenticate with the web guard to call the API.` with `--guard=web`, and the `curl` request has no `Authorization` header. When the run adds no route, the warning and the token command follow the guard that the existing routes of the models name in `->middleware('auth:<guard>')`: `--guard` only sets the guard of the routes to add. When the command finds no such guard, for example for routes in a group, it uses `sanctum`.
- The generated classes use the root namespace of the application, such as `MyApp\`. Before, the command replaced `App\` in the generated files, which gave `MyMyApp\`.
