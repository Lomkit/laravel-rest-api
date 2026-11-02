# Upgrade Guide

## Upgrading to 3.0 from 2.x

Version 3.0 is in development on the `3.x` branch. This guide lists each breaking change that is merged into that branch.

### Requirements

- PHP 8.3 or later.
- Laravel 13.30 or later. Applications on Laravel 12 stay on 2.x, and applications on an earlier Laravel 13 release update Laravel first: `composer update laravel/framework`.
- Laravel Scout 11.9 or later, when Scout is installed: Composer refuses earlier releases next to the package. Scout is only needed for the `ScoutEngine` text search, see [Search engines](#search-engines).

### Configuration

This section lists each change to `config/rest.php`. Compare your published file with `vendor/lomkit/laravel-rest-api/config/rest.php`, then apply the changes below.

`authorizations.enabled` and `gates.enabled` are removed. To turn the policies or the gates off for the whole application, add the `DisableAuthorizations` or the `DisableGates` trait to your base resource, `app/Rest/Resources/Resource.php`, which your resources extend:

```php
namespace App\Rest\Resources;

use Lomkit\Rest\Concerns\Resource\DisableAuthorizations;
use Lomkit\Rest\Concerns\Resource\DisableGates;
use Lomkit\Rest\Http\Resource as RestResource;

abstract class Resource extends RestResource
{
    use DisableAuthorizations;
    use DisableGates;
}
```

`Resource::isAuthorizingEnabled()` and `Resource::isGatingEnabled()` return `true` and no longer read the config. The traits override them, and so can a resource of yours.

The keys that change:

| Key | Change |
| --- | --- |
| `authorizations.enabled` | Removed. Use the `DisableAuthorizations` trait. |
| `gates.enabled` | Removed. Use the `DisableGates` trait. |
| `authorizations.strict` | Added. `false` by default, or the value of `REST_AUTHORIZATIONS_STRICT`. |
| `authorizations.cache.enabled` | Its default goes from `true` to `false`, or the value of `REST_AUTHORIZATIONS_CACHE_ENABLED`. |
| `limits` | Added, in three groups: `sizes`, `depths` and `totals`. |
| `search.max_nesting_depth` | Moved to `limits.depths.filters`. The `search` block is removed. |
| `documentation` | Removed with the documentation generator. |

`authorizations.cache.default`, `gates.key`, `gates.message.enabled`, `gates.names` and `precognition.enabled` keep their name and their default. The blocks are now in this order: `authorizations`, `gates`, `limits`, `precognition`.

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
        'enabled' => env('REST_AUTHORIZATIONS_CACHE_ENABLED', false),
        'default' => 5,
    ],
],

'gates' => [
    'key' => 'gates',

    'message' => [
        'enabled' => false,
    ],

    'names' => [
        'authorized_to_view' => 'authorized_to_view',
        'authorized_to_create' => 'authorized_to_create',
        'authorized_to_update' => 'authorized_to_update',
        'authorized_to_delete' => 'authorized_to_delete',
        'authorized_to_restore' => 'authorized_to_restore',
        'authorized_to_force_delete' => 'authorized_to_force_delete',
    ],
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
        'gates' => 7,
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
],

'precognition' => [
    'enabled' => false,
],
```

Authorizations:

- `authorizations.strict` turns strict mode on, see "Checks the request did not plan" in [Authorization](#authorization). Turn it on in your test suite, for example with `<env name="REST_AUTHORIZATIONS_STRICT" value="true"/>` in `phpunit.xml`.
- The cache of decisions between requests is off by default. A published file keeps its own value, `true` in the 2.x file: write `env('REST_AUTHORIZATIONS_CACHE_ENABLED', false)` there unless you want the cache. `REST_AUTHORIZATIONS_CACHE_ENABLED=true` turns it on. See "Cache" in [Authorization](#authorization).

Limits:

- The `limits` block sets the most entries of each list of a request, how deep filters, includes and relations nest, and two totals. See "Limits" in [Validation engine](#validation-engine) for what each key covers.
- `search.max_nesting_depth` moved to `limits.depths.filters`, with the same default of 1 and the same meaning. Move its value to the new key and remove the `search` block.

Published files:

- The package defaults merge into your published `config/rest.php` at every level. A nested key that your file leaves out, such as `gates.names.authorized_to_restore` or `limits.sizes.filters`, gets its default value. Before, a top-level key such as `gates` replaced all the defaults under it.
- Remove from your file the keys that 3.0 removed or moved: `authorizations.enabled`, `gates.enabled`, `search.max_nesting_depth` and the `documentation` block, after moving the value of `search.max_nesting_depth` to `limits.depths.filters`.
- A key under `limits` that the package does not know throws an exception that names it, so that a typo does not keep the default without notice: "The [rest.limits.sizes.filter] option does not exist." An unknown key elsewhere is ignored.

Types:

- Each request reads the values once, and checks their type. A value of the wrong type throws an exception that names the key. Before, the values were cast without a check: the string `"false"` turned strict mode, the cache, the gate messages and Precognition on.
  - `authorizations.strict`, `authorizations.cache.enabled`, `gates.message.enabled` and `precognition.enabled` take `true`, `false`, or the strings `"1"`, `"0"`, `"true"` and `"false"`. Anything else throws, `null` included: "The [rest.authorizations.strict] option must be a boolean."
  - `authorizations.cache.default` and each value of `limits` take an integer of 0 or more, or a string of digits, as `env()` gives it: "The [rest.limits.sizes.filters] option must be an integer of 0 or more."
  - `gates.key` takes a string that is not empty: "The [rest.gates.key] option must be a non-empty string."
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
3. Install `dedoc/scramble` 0.13. The package registers its extension, so `config/scramble.php` needs no change. If you added `Lomkit\Rest\Scramble\LomkitLaravelRestApiOperationExtension` to its `extensions`, you can remove it.

### Request context

Each package request now owns a `RestContext`. The context holds the request and one instance of each resource for that request. Resources read the request from their context, not from the container.

These items change:

- `Controller::newResource()` is removed. In a controller action, use `$request->context()->resource(static::resourceClass())`.
- `app(RestRequest::class)` no longer returns the request in progress. It builds a new request. In a resource, read the request from the method parameter or from `$this->context()->request`.
- A package request reads its resource from the class of the route controller, which must extend `Lomkit\Rest\Http\Controllers\Controller`. On another route, such as a closure route, the request throws `Lomkit\Rest\Exceptions\InvalidControllerException`. Before, it failed with a PHP error.
- The query builder methods that only exist on Eloquent (`mutate()`, `mutations()`, `applyMutation()`, `disableDefaultLimit()`) are declared on a new contract, `Lomkit\Rest\Contracts\EloquentQueryBuilder`. If you bind your own `QueryBuilder` implementation, it must implement `EloquentQueryBuilder`. The package resolves `EloquentQueryBuilder` through your `QueryBuilder` binding.

### Resource definitions

A resource holds no request state. Its context evaluates `fields()`, `scopes()`, `relations()`, `instructions()`, `actions()`, `limits()` and `text()` once per request, when they are first read, and keeps the results until the request ends. The next request evaluates them again, with its own user.

- The `calculatedFields`, `calculatedScopes`, `calculatedRelations`, `calculatedInstructions`, `calculatedActions`, `calculatedLimits`, `calculatedScoutFields` and `calculatedScoutInstructions` properties of resources are removed. Read the declarations with `getFields()`, `getScopes()`, `getRelations()`, `getInstructions()`, `getActions()` and `getLimits()`. They still take a request, but they evaluate the declarations with the request of the resource's context, whatever request they receive. `getScoutFields()`, `getScoutInstructions()` and `scoutInstruction()` are removed with the declarations they read, and so is the `Lomkit\Rest\Concerns\Resource\Scoutable` trait, see [Search engines](#search-engines).
- A serialized resource holds its own properties and an empty context, without its declarations. A queued or batched action therefore evaluates them again in the worker, where the request has no user: declarations that depend on the user see a guest there. Closures in the declarations, such as field rules, scope fields, relation constraints, or actions and instructions built with closures, stay out of the queued job. The action that runs and the field values it receives are still serialized with the job, so they cannot hold closures.
- `Resource` does not define `__sleep()`, and the `Lomkit\Rest\Concerns\Resource\ForgetsRequestState` trait is removed. A resource that overrides `__sleep()` must not call `parent::__sleep()`.

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
| json, jsonb, JSON cast | `Json::make('metadata')`, with or without a JSON cast on the model |

- `Json` works with or without a JSON cast on the model, or on the pivot class for a pivot field. When the model writes the attribute as it is, with no cast or a `string`, `encrypted` or `AsStringable` cast, and no set mutator, the field encodes the value before Eloquent writes it, and the response returns the decoded value. Any other cast, such as `array`, `json`, `collection`, `AsArrayObject`, `AsCollection`, `AsFluent`, `AsVector` or a cast class of the application, and a set mutator (`setMetadataAttribute()` or an `Attribute` with a `set` callback) receive the value as sent and encode it, as before. A `fillUsing()` callback still receives the value as sent. A `json` pivot column on the default `Pivot` class needs no custom pivot model.
- `getFields()` returns a `Lomkit\Rest\Fields\FieldSet` instead of an array of names. Call `->names()` to get the old list.
- `rest:resource` generates `ID::make()` with the key name of the model, `ID::make('id')` by default. See [Generators](#generators).
- `Lomkit\Rest\Fields\DateTime` has the same short name as PHP's `\DateTime`. In a file that uses both, alias one of them.
- A field can declare `label()` and `messages()`. The label names the field's key in validation messages, and the messages replace Laravel's text for the rules of that key. See "Labels and messages" in [Validation engine](#validation-engine).

### Typed inputs

Scopes, instructions and actions declare their inputs with the same `Field` objects as resources. The payload does not change: scope parameters stay a positional list, and instruction and action fields stay a list of `{name, value}` entries. Input fields take `rules()` only: `creationRules()` and `updateRules()` describe writes, so declaring them on an input field throws `InvalidFieldException`.

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

Instructions:

- `fields()` returns `Field` objects instead of a map of rules: `['min_views' => ['integer']]` becomes `[Integer::make('min_views')]`. The field rules are the type rules, `nullable` and `rules()`; rules that compare fields, such as `required_if:status,published`, `date_equals:since` or `Rule::date()->after('since')`, compare with the other inputs of the instruction, as described under Validation engine.
- `handle()` and `handleEngine()` receive a name-to-value map, with each value cast to its field type and defaults filled in. A field that is absent and has no default is missing from the array, so read optional fields with `$fields['name'] ?? null`. An entry sent without `value` counts as absent.
- `Builder::instruction()` throws `Lomkit\Rest\Exceptions\InvalidInstructionException`, a `\LogicException`, for a name the resource does not declare.
- An instruction applied to an include receives the relation's Eloquent builder.

Actions:

- `fields()` returns `Field` objects instead of a map of rules.
- `handle()`, `withBatch()` and queued jobs all receive the same cast name-to-value map, with defaults filled in. Before, queued and batched actions received the raw list of `{name, value}` entries.
- `OperateRequest::resolveFields()` takes no argument. It returns the cast map of `payload()`, see [Validation engine](#validation-engine), and `Action::handleRequest()` calls it. Like `payload()`, it throws a `LogicException` before the request passed validation. Before, it read the raw input at any time.
- The `fields()` of an action run once per request, during validation. Before, they ran twice. The request keeps the fields and the action does not, so a queued job still carries no declarations.

Instructions and actions:

- `Lomkit\Rest\Concerns\Fieldable` and its `field()` helper are removed. Use `getFields($request)->find($name)`, which returns the `Field` or `null`. `getFields()` returns a `FieldSet` built on each call.
- The fields are not bound to a model, so an `Enum` field names its enum class: `Enum::make('status', PostStatus::class)`.
- For actions, an entry without a name, or that is not an object, now gives a 422 under `fields.<i>.name` instead of a 500, as it already did for instructions. A name that is not a string fails with `must be a string`.
- `rest:action` and `rest:instruction` generate a `fields()` method that returns `[]`. Before, it declared a required `id` field that the action or the instruction did not use. See [Generators](#generators).

Meta:

- `Lomkit\Rest\Concerns\Metable` is removed. Actions, instructions and scopes use `Lomkit\Rest\Concerns\HasMeta`: `withMeta([...])` becomes `meta([...])`, which merges, and the `meta()` getter becomes `getMeta()`. The `$meta` property is private.

Details: scopes, instructions and actions list their inputs as entries, see [Details](#details).

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

- `searchQuery()`, `mutateQuery()`, `destroyQuery()`, `restoreQuery()` and `forceDestroyQuery()` return `Illuminate\Contracts\Database\Eloquent\Builder`. Add the return type to every override, including the five hooks that `rest:base-resource` wrote in `app/Rest/Resources/Resource.php` under 2.x. A hook that only returns `$query` can be deleted instead: `rest:base-resource` no longer generates them. The parameter keeps the contract type, and the return type may be the Eloquent `Builder` instead of the contract:

  ```php
  use Illuminate\Contracts\Database\Eloquent\Builder;

  public function searchQuery(RestRequest $request, Builder $query): Builder
  {
      return $query->where('team_id', $request->user()->team_id);
  }
  ```

- An override must return the builder: one that changes `$query` and returns nothing now throws a `TypeError`. The package ignores the returned builder, so apply the constraints to `$query` itself, as before.

Responses:

- `$response` is declared `public static string`, and `newResponse()` returns `Lomkit\Rest\Http\Response`. The class it names must extend `Lomkit\Rest\Http\Response`, otherwise the endpoints throw a `TypeError`.

Validation rules:

- `rules()`, `createRules()` and `updateRules()` return `array`.

Actions, instructions and relations:

- `Action`, `Instruction` and `BatchableAction` declare native types:
  - a redeclared property keeps its type: `public ?string $name`, `public bool $standalone`, `public bool $targeted`, `public int $chunkCount`;
  - an override declares its return type: `handle(): void`, `withBatch(): void`, `name(): string`, `uriKey(): string`, `standalone(): static`, `targeted(): static`, `isStandalone(): bool`, `isTargeted(): bool`, `handleRequest(): int`.
- An action's `handle()` returns `void`. Before, a synchronous action that returned `false` stopped the remaining chunks; the chunks now always run.
- `make()` returns `static`. An action, instruction or relation that redefines it must declare `: static`.
- On relations, `requiredOnCreation()`, `prohibitedOnCreation()`, `requiredOnUpdate()` and `prohibitedOnUpdate()` return `static`. A relation that overrides one of them must declare `: static`. Their callbacks receive the request and the related resource: `fn (RestRequest $request, Resource $resource): bool`.
- The public `relationRequiredOnCreationCallback`, `relationProhibitedOnCreationCallback`, `relationRequiredOnUpdateCallback` and `relationProhibitedOnUpdateCallback` properties of relations are removed: the constraints are now private. Set them with `requiredOnCreation()`, `prohibitedOnCreation()`, `requiredOnUpdate()` and `prohibitedOnUpdate()`, and read them with `isRequiredOnCreation()`, `isProhibitedOnCreation()`, `isRequiredOnUpdate()` and `isProhibitedOnUpdate()`.
- `resource(Resource $resource)` returns `static` on actions, instructions and requests.

Other resource methods:

- `newModel(): Model`, `relation(): ?Relation`, `getRelations(): array` and `instruction(): ?Instruction`.
- `performDelete()`, `performRestore()` and `performForceDelete()` return `void`.
- `paginate()` returns `Illuminate\Contracts\Pagination\LengthAwarePaginator`, so an override cannot return a simple or a cursor paginator.

Controller endpoints:

- `details()` and `mutate()` return `array`, `operate()` returns `Illuminate\Http\Response`, and `search()`, `destroy()`, `restore()` and `forceDestroy()` return `Lomkit\Rest\Http\Response`.

Requests:

- `authorize()` returns `bool|Illuminate\Auth\Access\Response`, so it can still return `Response::deny()`.
- `rules()` and `resolveFields()` return `array`.
- `Lomkit\Rest\Http\Requests\ActionsRequest` is removed. Nothing used it: the operate hooks receive an `OperateRequest`.

Service provider and facade:

- `RestServiceProvider::register()` and `boot()` return `void`, and the `Rest` facade's `getFacadeAccessor()` returns `string`.

Query builder:

- `Lomkit\Rest\Query\Builder::$resource` is declared `protected Resource $resource`, where `Resource` is `Lomkit\Rest\Http\Resource`. A query builder that extends `Builder` and redeclares `$resource` keeps that type.
- `Builder::disableDefaultLimit()` takes `bool $disable`, as the `EloquentQueryBuilder` contract declares it.

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
- The checks of field, scope, instruction and action field names use a new rule, `Lomkit\Rest\Rules\OneOf`, instead of `Rule::in()`. The message is still `validation.in` ("The selected :attribute is invalid."), but a custom message written for the `in` rule of these attributes no longer applies.

Query builder and requests:

- `disableSecurity()` is removed from `Lomkit\Rest\Query\Builder` and from the `EloquentQueryBuilder` contract. `searchQuery()` applies to every search.
- `RestRequest::isScoutMode()` is removed. `$request->context()->text($resource)` returns the text search of the resource for the request, a `Lomkit\Rest\Text\ResolvedText`: its `usesEngine()` is true when the request runs its text on a search engine, see [Search engines](#search-engines), and its `query` holds the text of the search, or `null` when the request has no text to search.
- The search parser validates `text` and `trashed` at the root of the search, and an include refuses them: "The search.includes.0.text field is prohibited."
- `Resource::relation()` returns `null` for a dotted path whose first relation is not declared. Before, it failed with an error.

### Text search

A resource declares its text search with `text()`. With the `Contains` and `FullText` strategies, `searchable()` marks the fields to search, and the text becomes one more condition of the SQL search: filters, scopes, instructions, includes, aggregates, sorts, pagination, `searchQuery()` and actions work with it as they do without it. `ScoutEngine` runs the text on a search engine instead, see [Search engines](#search-engines).

```php
use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Text\Contains;
use Lomkit\Rest\Text\FullText;
use Lomkit\Rest\Text\TextSearch;

public function fields(RestRequest $request): array
{
    return [
        ID::make('id'),
        Text::make('name')->searchable(),
        Text::make('description')->searchable(),
        Integer::make('price'),
    ];
}

public function text(RestRequest $request): ?TextSearch
{
    return FullText::make()
        ->index('name', 'description')
        ->otherwise(Contains::make());
}
```

Declaration:

- `Resource::text(RestRequest $request): ?TextSearch` returns `null` by default. A resource without text search refuses `search.text` with a 422, "The search.text field is prohibited.". A model that uses `Laravel\Scout\Searchable` no longer gives its resource a text search: declare `text()` on the resource. If one of your resources already has a method named `text()`, rename it.
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
- With `Contains` and `FullText`, the full search grammar works with text: filters with `or`, `nested`, relation paths and every operator, scopes, instructions, includes, aggregates, sorts, `page` and `limit`. `total` counts the matching rows. The text narrows the searched resource only: it does not narrow includes, aggregates or relation filters, and `text` in an include gives a 422. `viewAny` is checked before the search runs, and `searchQuery()` applies as in any search.
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

- `Lomkit\Rest\Query\Builder::applySorts($sorts, ?ResolvedText $text = null)` takes the text search of the request as a second parameter, to sort by `@relevance`. An override of `applySorts()` must declare it.

Scramble:

- The search body documents `text` only on the resources with a text search: `value` and the `mode`s of the strategy. The `fields` key of `text`, which the package never read, is removed.
- `trashed` is documented on the resources whose model uses `SoftDeletes`, and the sort `field` lists `@relevance` when the strategy orders by relevance.

### Search engines

`Lomkit\Rest\Text\ScoutEngine` runs the text search of a resource on the search engine of its Laravel Scout model. The engine matches the text, applies the filters and the sorts, pages the results and counts them, then the package loads the rows of the page in one SQL query. It replaces the Scout text search that 2.x ran on every resource whose model used `Laravel\Scout\Searchable`.

```php
use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Fields\Number;
use Lomkit\Rest\Text\Engine\EngineQuery;
use Lomkit\Rest\Text\ScoutEngine;
use Lomkit\Rest\Text\TextSearch;

public function text(RestRequest $request): ?TextSearch
{
    return ScoutEngine::make()
        ->filterable([Integer::make('category_id'), Number::make('price'), 'status'])
        ->sortable(['price'])
        ->perimeter(
            fn (EngineQuery $query, RestRequest $request) => $query->where('team_id', '=', $request->user()->team_id),
            [Integer::make('team_id')],
        );
}
```

Declaration:

- The model uses `Laravel\Scout\Searchable`, and its engine, the result of `searchableUsing()`, is a Scout engine or a class that extends one: `collection`, `database`, `meilisearch`, `typesense`, `algolia`, `turbopuffer` or `null`. Another engine is refused, because the package cannot know which filters and sorts it runs.
- `filterable()` lists the index attributes that clients may filter on: fields, such as `Integer::make('category_id')`, or the names of fields of the resource, such as `'status'`, which take the type and the operators of that field. An attribute is an `ID`, `Integer`, `Number`, `Boolean`, `Text` or `Enum` field without `filterUsing()` or `sortUsing()`. `Date`, `DateTime`, `Json` and the app types that extend a field type are refused: index a date as a timestamp and declare it as an `Integer`. A name starts with a letter or `_` and holds only letters, digits and `_`, and `__soft_deleted` is reserved. An `Enum` field declared here reads its enum class from the casts of the model.
- `sortable()` names the `filterable()` attributes that clients may sort on.
- The index perimeter is required: declare `perimeter()` or `withoutPerimeter()`, and not both. The closure of `perimeter()` receives the `EngineQuery` of each search and the request. Its conditions narrow every search of the resource, its total and its pages included, and clients cannot widen them. Its second argument lists the attributes that only the perimeter uses: clients cannot filter or sort on them.
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
- `includes`, `aggregates`, `selects` and `gates` are validated and applied as in a SQL search, on the rows of the page.

Pages and totals:

- The engine pages the results with `search.limit` and `search.page`. `total` and `last_page` are the engine's, and the rows keep the order of the engine.
- The package loads the rows of the page in one SQL query, by their scout key (`getScoutKeyName()`), with `searchQuery()`, the global scopes of the model and its soft deletes, as `search.trashed` sets them. A row that the index returns and that this query leaves out, for example while the index is behind the database, is left out of `data`, and `total` still counts it: a page can hold fewer rows than `limit`. Put the constraints of `searchQuery()` in the perimeter too.
- Scout indexes a model when it is saved, whatever the global scopes of the model. On the `meilisearch`, `typesense`, `algolia` and `turbopuffer` engines, a row that a global scope leaves out can therefore be in the index: it is left out of `data`, and `total` still counts it. Keep these rows out of the index with `shouldBeSearchable()`, or add the condition of the scope to the perimeter.
- The keys follow `getScoutKeyType()`: `int` or `integer` gives integer keys, and any other type string keys. Scout returns the key type of the model by default, so a model whose scout key is not its primary key, such as a `code` column, declares `getScoutKeyType()`. A key that does not match the type throws `Lomkit\Rest\Exceptions\InvalidResourceException`.
- The package builds Scout's `Laravel\Scout\Builder` itself: an override of the model's `search()` method is not used, and the overrides of `getScoutModelsByIds()` and `queryScoutModelsByIds()` do not apply to the rows of the page. `Resource::paginate()` serves the SQL searches only.

Engines:

- `collection` and `database`: every operator and sort, exact totals. On PostgreSQL, Scout's `database` engine compares every key of `toSearchableArray()` with `ilike`, so return text columns only there.
- `meilisearch`: exact totals, up to the `pagination.maxTotalHits` setting of the index (1000 by default). The index declares the `filterable()` and perimeter attributes in `filterableAttributes` and the `sortable()` attributes in `sortableAttributes`, for example through `scout.meilisearch.index-settings` and `php artisan scout:sync-index-settings`, which adds `__soft_deleted` when `scout.soft_delete` is `true`.
- `typesense`: exact totals. The model needs `query_by` in `scout.typesense.model-settings.<model class>.search-parameters`, or in `typesenseSearchParameters()`, otherwise the declaration throws. A page holds at most 250 rows: a larger `search.limit` gives a 422, so keep the `limits()` of the resource at 250 or below. A search takes at most 3 sorts.
- `algolia`: exact totals, no sorts: Scout's Algolia engine ignores them, so `sortable()` throws, and `@relevance` alone is accepted.
- `turbopuffer`: no sorts, because Turbopuffer refuses sorts next to a text: `sortable()` throws, and `@relevance` alone is accepted. A page must end within the first 10,000 results, otherwise `search.page` gives a 422. The total that Scout reports counts the documents that match the filters, not the text: `total` and `last_page` are given as Scout reports them, and can exceed the matching rows.
- `null`: an empty page and a total of 0, without SQL query.
- Meilisearch and Typesense compare filter strings without regard to case: prefer `Integer`, `ID`, `Boolean` or `Enum` perimeter attributes.

Engine queries:

- The perimeter and the instructions receive a `Lomkit\Rest\Text\Engine\EngineQuery`, not Scout's builder:
  - `where($attribute, $operator, $value)` takes `=`, `!=`, `<`, `>`, `<=` or `>=`, as a string or a `FilterOperator`. `whereIn($attribute, $values)` and `whereNotIn($attribute, $values)` take a list.
  - The attribute must be declared in `filterable()` or in the perimeter, with this operator. The value is converted to the type of the attribute, a backed enum to its value. Another attribute or operator, `null`, an array, an object or a number that is not finite throws `Lomkit\Rest\Exceptions\InvalidEngineQueryException`, a `\LogicException`.
  - The conditions add up and never replace each other: two `whereIn()` on one attribute keep the values that both lists hold, and two `whereNotIn()` exclude the values of both lists. An empty `whereIn()` list matches nothing, and the search then returns an empty page without calling the engine. An empty `whereNotIn()` list changes nothing.
  - `orderBy($attribute, $direction)` sorts on a `sortable()` attribute.
  - `options([...])` adds options for the engine to the options already set. An option set again with another value throws, and so do the options that the package sets itself, such as `filter`, `sort`, `page` and `hitsPerPage` on Meilisearch.
  - It has no `query()` or search callback.

Instructions:

- `instructions()` is the only list of instructions. An instruction applies to a text search on a search engine when it implements `Lomkit\Rest\Instructions\AppliesToEngine`, whose `handleEngine(array $fields, EngineQuery $query): void` receives the cast fields, as `handle()` does. Another instruction gives a 422 there.
- An instruction that implements `AppliesToEngine` and does not override `handle()` only applies to these searches: it gives a 422 in a SQL search, such as a search without text or with a blank text, an include, an action without text, or the search of a `Contains` or `FullText` resource.

  ```php
  use Illuminate\Database\Eloquent\Builder;
  use Lomkit\Rest\Fields\Number;
  use Lomkit\Rest\Instructions\AppliesToEngine;
  use Lomkit\Rest\Instructions\Instruction;
  use Lomkit\Rest\Text\Engine\EngineQuery;

  class PricedUpTo extends Instruction implements AppliesToEngine
  {
      public function fields(RestRequest $request): array
      {
          return [Number::make('max')->rules('required')];
      }

      public function handle(array $fields, Builder $query): void
      {
          $query->where('price', '<=', $fields['max']);
      }

      public function handleEngine(array $fields, EngineQuery $query): void
      {
          $query->where('price', '<=', $fields['max']);
      }
  }
  ```

- In the details, each instruction adds `text`: whether it applies to a search with text on this resource. It is `false` on a resource without text search; on `Contains` and `FullText`, `true` unless the instruction only applies to search engines; on `ScoutEngine`, `true` for the instructions that implement `AppliesToEngine`.

Actions:

- An action whose search has a text on a search engine resolves its targets before it runs, is queued or starts a batch: it reads the keys from the engine in pages of `chunkCount`, then loads each chunk in SQL by key, with `searchQuery()`, the global scopes and `search.trashed`. An action that changes indexed attributes still acts on each matching row once, and `impacted` is the number of models given to `handle()`.
- A search that matches more than `maxResources` resources gives a 422 under `search`, and the action does not run: "This action can be run on at most 1000 resources; narrow the search." With `search.limit`, the action acts on the first `limit` results.
- Meilisearch and Algolia count the results up to a setting of the index: `pagination.maxTotalHits` on Meilisearch and `paginationLimitedTo` on Algolia. Without `search.limit`, the action reads this setting, so the key of the application must be allowed to read the settings of the index. Set it above the `maxResources` of the actions: when it is not above, and the search reaches it, the action gives a 422, "The search engine returns at most 1000 results for this search; narrow the search.". When the settings cannot be read, the action gives a 422, "The search engine does not report how many results this search can reach; give the search a limit.".
- On Turbopuffer, whose total does not count the text, the action reads the keys until a page is not full.
- When the results change while the action reads them, it gives a 422, "The search results changed while the action was resolving them; run the action again.". Set `scout.after_commit` to `true`, so that the index changes once the database transactions commit.
- The actions of `Contains` and `FullText` resources do not change: they still act on the rows of the SQL search, in chunks.

From the Scout text search of 2.x:

| 2.x | 3.0 |
| --- | --- |
| `use Searchable` on the model enables `search.text` | `text()` returns `ScoutEngine::make()` |
| `scoutFields()` | `ScoutEngine::make()->filterable([...])->sortable([...])` |
| `scoutInstructions()` | `instructions()`, with `AppliesToEngine` on each instruction |
| `handleScout(array $fields, Builder $query)` | `handleEngine(array $fields, EngineQuery $query)` |
| `searchScoutQuery()` | `ScoutEngine::make()->perimeter(...)` |
| `isModelSearchable()` | removed |

- A resource that still declares `scoutFields()`, `scoutInstructions()` or `searchScoutQuery()` throws `Lomkit\Rest\Exceptions\InvalidResourceException` when a request first reads its declarations, and an instruction that declares `handleScout()` throws `InvalidInstructionException` when its resource reads its instructions. The message names the method and its replacement.
- These methods and `isModelSearchable()` no longer exist on the package classes: an override marked `#[\Override]` is a fatal error when PHP loads the class. Delete them.
- `Lomkit\Rest\Query\ScoutBuilder` is removed, and the `search()` and `toBase()` methods of `Lomkit\Rest\Contracts\QueryBuilder` no longer return it.
- `Lomkit\Rest\Contracts\EloquentQueryBuilder` adds `searchKeys(array $keys, array $parameters = []): Builder`, which loads the rows of an engine page by key. `Lomkit\Rest\Query\Builder` implements it: an implementation of the contract that does not extend it must add the method.
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
- `SearchRequest::rules()` returns rules on explicit keys, such as `search.filters.0.field`, instead of a rule tree. `OperateRequest::rules()` returns rules on explicit keys too: the search keys, `resources.0`, `resources.1`, …, `fields.<name>`, and `fields.0.name`, `fields.1.name`, … in place of `fields.*.name`. For an action that is neither standalone nor targeted, `search` has a rule only when the request sends it. The errors of `fields.0.name`, `fields.1.name`, … now come before the errors of a key that an override adds after `...parent::rules()`. Before, they came after them. So the `message` of the 422 response can start with another error. `MutateRequest::rules()` returns rules on explicit keys too, such as `mutate.0.operation` and `mutate.0.relations.products.1.key`, instead of a rule tree, and a key that an override adds after `...parent::rules()` reports its errors after those of the package keys. On a key that the package validates in `mutate`, such as `mutate.0.attributes.name`, the rules that the override adds run after the package rules of that key, whether the override names the key or writes a wildcard rule such as `mutate.*.attributes.name`. Before, they ran first, except for a wildcard rule written after `...parent::rules()`. So when an added rule and a package rule of one key both fail, the first message of the key, and the `message` of the 422 response, can change. `relations` has a package rule only when the entry sends it: a rule that an override adds on `mutate.*.relations`, such as `required`, also runs on an entry without `relations`.
- An override of `rules()` must call the parent: without it, the search is not read, and on `OperateRequest` and `MutateRequest` the operate or mutate payload is not read and `payload()` throws. A rule that the override adds for a key that the package validates is added to the package rules of that key: `[...parent::rules(), 'search.limit' => ['max:10']]` keeps the package rules of `search.limit` and adds `max:10`. The package rules of a parsed key, such as a search key, `fields.0.name` or `mutate.0.operation`, apply even when the override replaces or leaves out its entry. A wildcard rule that the override adds, such as `resources.*`, `fields.*` or `mutate.*.attributes.name`, runs on each item, whether it is written before or after `...parent::rules()`. It does not run when the list that its first `*` stands for, such as `mutate`, is over its limit. A rule with more than one `*`, such as `mutate.*.relations.products.*.attributes.name`, still runs on the entries of a nested list that is over its limit, such as `mutate.0.relations.products`. For a key without `*`, write `...parent::rules()` first: the parent's entry replaces an entry written before it, and `+` keeps the parent's entry, so the rule of the override is lost there.
- A wildcard rule that the override adds on the entries of an instruction's `fields`, such as `search.instructions.*.fields.*`, also meets the named input values, such as `search.instructions.0.fields.min_views`. To check the entries only, write the rule on `search.instructions.*.fields.*.value` or `.name`, or give the rule to the field that the instruction declares in `fields()`.
- `SearchRequest::payload()` returns the validated search as a `Lomkit\Rest\Search\SearchNode`. Its parts are typed nodes of the same namespace: `FilterGroup`, `FilterCondition` and `RelationFilterGroup` (each a `FilterNode`), `SortNode`, `AggregateNode`, `IncludeNode`, `ScopeNode` and `InstructionNode`. A `RelationFilterGroup` holds its `Relation`, `exists` and the `FilterGroup` of its `nested`. `payload()` throws a `LogicException` before the request passed validation: "The search was read before the request passed validation."
- `OperateRequest::payload()` returns the validated operation as a `Lomkit\Rest\Actions\OperationNode`. The node has `action`, `fields`, a `Lomkit\Rest\Fields\InputValues`, and `target`. The target is one of these: a `Lomkit\Rest\Search\SearchNode` for an action that is neither standalone nor targeted, which is empty when `search` is left out; the `Lomkit\Rest\Validation\KeyList` of a targeted action; `null` for a standalone action. `keys()` returns the `KeyList`. `payload()` throws a `LogicException` before the request passed validation: "The operation was read before the request passed validation."
- `MutateRequest::payload()` returns the validated mutation as a list of `Lomkit\Rest\Mutations\MutationNode`, one per entry of `mutate`. A node has `operation` (a `Lomkit\Rest\Fields\MutationOperation`), `key`, `attributes`, `pivot`, `withoutDetaching`, `relations`, which holds the nodes of each relation that the entry sends, and `depth`. `context()` returns its `MutationContext`, and `toArray()` rebuilds the entry as an array. `payload()` throws a `LogicException` before the request passed validation: "The mutation was read before the request passed validation."
- Every package request records its validation pass in `passedValidation()`. An override of `passedValidation()` must call the parent.
- To adjust the validator, use `withValidator()`, or override `createDefaultValidator()` and call the parent. A request that defines `validator()` builds the validator itself: it must use `validationData()` and `validationRules()`. The `label()` and `messages()` of fields do not apply there, and Laravel names its keys, such as `fields.status` for an input field.
- Every key that the package parses in `mutate` is named as written in messages, at every level, as Laravel names wildcard keys, unless the request's `attributes()`, the field's `label()` or a `validation.attributes` translation line names it. Examples: `mutate.0.without_detaching`, `mutate.0.attributes.published_on`, `mutate.0.relations.latestLoan.operation`, `mutate.0.relations.lineItems.0.operation`, a relation that the entry does not send, such as `mutate.0.relations.featuredProducts`, and an explicit key of the resource rules, such as `mutate.0.attributes.metadata.source_url`. A key that Laravel expands from a wildcard rule of the request or of the resource rules, such as `mutate.0.attributes.metadata.tags.1`, is named as written too. The package gives a parsed key its name as written only where Laravel's formatting would change the key: when it holds `_`, whitespace or an uppercase letter. Laravel's formatting leaves the other parsed keys as written. `Validator::setImplicitAttributesFormatter()` applies to a key that Laravel expands only when no other name applies to it. So it does not apply to a parsed key that a wildcard rule meets when the key holds `_`, whitespace or an uppercase letter, such as `mutate.0.attributes.published_on` under `mutate.*.attributes.published_on`, or when its field declares `label()`. It still applies to the other parsed keys, such as `mutate.0.attributes.name` under `mutate.*.attributes.name` or `mutate.0.relations.products.0.operation` under `mutate.*.relations.products.*.operation`.
- The input values added by `validationData()` also appear in `validated()` and `safe()`, under `fields.<name>` for an action. `resolveFields()` returns the cast map of `payload()`, and `handle()` receives the same map.
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
- A standalone or targeted action now reads the `text()` declaration of its resource during validation, as any other action does. A `text()` declaration that throws `Lomkit\Rest\Exceptions\InvalidTextSearchException` now also fails these actions.

Mutate payload:

- Inside a mutate entry, a key that the package does not know gives a 422 on that key, named as written: "The mutate.0.atributes field is prohibited." Before, the key was ignored. The keys of an entry are `operation`, `attributes`, `key`, `relations` and `without_detaching`, plus `pivot` in an entry of a `BelongsToMany`, `MorphToMany` or `MorphedByMany` relation, and `type` in an entry of a `MorphTo` relation, which is accepted and not passed to the write. `pivot` and `type` get the same 422 in the other entries. The other keys of the entry are still validated.
- An entry or a to-one relation given as a list gets this 422 on each of its items, then the errors of the keys it lacks: "The mutate.0.relations.category.0 field is prohibited." and "The mutate.0.relations.category.operation field is required.".
- A rule that a request adds in `rules()` for a key that the package does not know inside a mutate entry, such as `'mutate.*.flag' => ['boolean']`, does not make that key valid: the key keeps the 422 above.
- A name in `relations` that the resource does not declare gives "The suppliers key is not valid for the mutate.0.relations field." on `relations`. The message names its field as other messages do, such as `mutate.0.relations.latestLoan.relations` under a to-one relation.
- `mutate`, each to-many relation and each `key` list must be JSON lists. An object gives a 422 on the list, "The mutate field must be a list.", and its entries are not checked: a denied creation in it gets this 422, where it got a 403 before. Before, an object was read entry by entry, and `{"*": ...}` gave "The * key is not valid for the mutate field.".
- A `key` is typed like the `ID` field of its resource: "The mutate.0.key field must be an integer." (or a string, a UUID or a ULID, by key type; a resource without an `ID` field uses the model's key type). A `null` key gets the type message too. Before, a key of the wrong type gave "The selected mutate.0.key is invalid.", or a 500 on PostgreSQL.
- A key list is accepted only in an entry of a to-many relation. At the root and in a to-one relation, a list gets the type message. Before, it was validated, and then the request failed. Each item of a key list gets its type message under `key.0`, `key.1`, ….
- The keys are checked with one query per resource class and request, per 1,000 keys, through the `mutateQuery()` of their resource and the global scopes of the model, at the root and under relations. A key that this query does not return, a soft-deleted key included, gives "The selected mutate.0.key is invalid.". A key list with a missing key gives one error on its `key`, such as "The selected mutate.0.relations.products.0.key is invalid.". Two forms of one UUID or ULID key that differ only by case are checked apart, each in a query of its own, and each passes only when the database matches it as sent. A `Precognition-Validate-Only` request whose names leave out every `key` runs no key query.
- `MutateRequest` sets `$request->resource` in `rules()` and has `keysQuery(Resource $resource): Illuminate\Database\Eloquent\Builder`. The `mutateQuery()` of each resource class whose keys the payload holds runs during validation, before `beforeMutate()`, once per class.
- On `create`, `key` is not read: it gets only "The mutate.0.key field is prohibited when mutate.0.operation is create.", and no key query runs for it. Before, a key list there also got the `values` limit error and counted toward `totals.entries`.
- The query builder's `mutate()` receives the entries of `payload()`, rebuilt as arrays by `MutationNode::toArray()`: the keys that the entry sent, in the order `operation`, `key`, `attributes`, `pivot`, `without_detaching`, `relations`, with integer keys as integers. The `type` of a `MorphTo` entry is accepted and left out of these arrays. The relation hooks and the `mutating()` and `mutated()` hooks of the resource receive the same arrays. A change to the request input after validation, such as in `beforeMutate()`, no longer changes what is written.

Limits:

- Each list of a request accepts a limited number of entries, filters, includes and relation operations nest up to a set depth, and two totals bound one request. The values are in the new `limits` block of `config/rest.php`, in three groups, `sizes`, `depths` and `totals`. See [Configuration](#configuration) for the block, its defaults and the values it accepts.
- Each key of `sizes` applies to these lists, at every level of a search, including the search of an include and of an action:
  - `filters`: `search.filters`, each `nested` group, and the `filters` of includes and aggregates;
  - `sorts`, `selects`, `scopes`, `aggregates`, `includes`, `instructions` and `gates`: the list of that name;
  - `fields`: the `fields` of an action and of each instruction;
  - `values`: the values of an `in`, `not in`, `between` or `not between` filter, and the `key` list of an entry of a to-many relation in `mutate`;
  - `mutate`: the entries of `mutate`;
  - `relation_entries`: the entries of each to-many relation in `mutate`;
  - `resources`: the keys of `destroy`, `restore` and `forceDestroy`. A targeted action uses its `$maxResources` property instead.
- `depths` sets how deep filters, includes and relation operations nest: `filters`, `includes` and `mutations`.
- `totals` sets two totals:
  - `filters`: the filters of one search, at every depth and in its includes and aggregates;
  - `entries`: the entries of every list in `sizes` in one request, except `resources`.
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

- `destroy`, `restore`, `forceDestroy` and targeted actions read `resources` the same way. `resources` is required and must be a list of keys typed like the resource's `ID` field.
- The keys are checked with one query per 1,000 keys, after authorization, and only when `resources` is validated: a `Precognition-Validate-Only` request that leaves `resources` out runs no key query.
- The query is that of the endpoint, with the global scopes of the model: `destroyQuery()`, `restoreQuery()` or `forceDestroyQuery()`, with the trashed rows on `restore` and `forceDestroy`, or `searchQuery()` for a targeted action.
- The `ID` field sets the type of the keys, whatever the `$keyType` of the model. Integer keys are written into the query as integers, and other keys are bound as sent. UUID and ULID keys that differ only by case are the same key. A key of the `string` type is found only when the query returns it exactly as sent. An `ID` whose key type does not match the column, such as `keyType('string')` on an integer column, finds no key.
- A key that the query does not return gives a 422, "The selected resources.0 is invalid.":
  - on `destroy`, a key that `destroyQuery()` or a global scope leaves out, a trashed key included. Before, it passed validation and was skipped;
  - on `restore` and `forceDestroy`, a key that does not exist or that the hook leaves out. Before, it was skipped;
  - on a targeted action, a key that `searchQuery()` leaves out. Before, it was skipped and `impacted` was lower.
- A repeated key gives "The resources.0 field has a duplicate value." on every copy, on `restore`, `forceDestroy` and targeted actions too. `destroy` already gave it. On every endpoint, two UUID or ULID keys that differ only by case are repeated.
- On `destroy`, a repeated value that is not a valid key gets its type message only. Before, it also got "has a duplicate value.".
- An object in place of a list gives "The resources field must be a list.", on every endpoint that takes keys.
- `destroy`, `restore` and `forceDestroy` accept at most `rest.limits.sizes.resources` keys: "The resources field must not have more than 1000 items." A targeted action over its `$maxResources` gets only the error on `resources`. Before, every key was also checked.
- On a targeted action, a key of the wrong type gives "The resources.0 field must be an integer." (or a string, a UUID or a ULID, by key type). Before, it gave "The selected resources.0 is invalid.", and a 500 on PostgreSQL.
- A custom message for `exists` or `distinct` on `resources.*` no longer applies, see "Limits" above.
- `OperateRequest::rules()`, and the `rules()` of `DestroyRequest`, `RestoreRequest` and `ForceDestroyRequest`, return rules on `resources.0`, `resources.1`, … instead of `resources.*`.
- The query hooks of `destroy`, `restore` and `forceDestroy` now run during validation, before `beforeDestroy()`, `beforeRestore()` and `beforeForceDestroy()`, and run again to load the models. `$request->resource` is set in `rules()`.
- The deletion endpoints load their models from the query of the endpoint, limited to the parsed keys in the same way as the key query.
- A targeted action loads its models through the search query of the resource, limited to the parsed keys in the same way. Before, the `$keyType` of the model typed the keys of that load: with UUID keys on a model that keeps the default `int` key type, an action could act on other rows on MySQL, and failed on PostgreSQL. A queued or batched action restores its models in the worker through Laravel's `SerializesModels`, which types the keys by the model's `$keyType`: a model with UUID, ULID or string keys still declares `protected $keyType = 'string'`, or uses `HasUuids` or `HasUlids`.
- `OperateRequest::operateRules()` and `targetKeys()` are removed. `$request->payload()->keys()` returns the parsed keys of a targeted action, a `KeyList`, see "Requests" above. `payload()` throws a `LogicException` before the request passed validation, and `keys()` throws "The request has no resource keys." on an action that is not targeted. A targeted action reads them. `OperateRequest` no longer uses the `ParsesSearch` and `ParsesResourceKeys` traits, and keeps `keysQuery()`. Every package request records its validation pass in `passedValidation()`, so an override of `passedValidation()` must call the parent.
- `DestroyRequest`, `RestoreRequest` and `ForceDestroyRequest` have `payload(): KeyList` (`Lomkit\Rest\Validation\KeyList`) and `keysQuery(Resource $resource): Illuminate\Database\Eloquent\Builder`. The `keys` of a `KeyList` hold the parsed keys, and its `constrain()` method limits an Eloquent query to them. `payload()` throws a `LogicException` before the request passed validation. The request records its validation pass in `passedValidation()`, as every package request does, so an override of `passedValidation()` must call the parent.
- The `ValidatesResourceKeys` trait and the `destroyRules()`, `restoreRules()` and `forceDestroyRules()` methods are removed. Override `rules()` and call the parent instead. A `resources.*` rule added there runs on each key, next to the rules of the package, and does not replace them. It does not run when `resources` is over its limit or is not a list.
- The `key` of a mutation entry is typed by the `ID` field of its resource and checked in the same way, through the `mutateQuery()` of its resource, see "Mutate payload" above. A repeated key is accepted there, and two forms of one UUID or ULID key that differ only by case are checked apart.

### Authorization

Each request now has one authorizer, which the resources of the request share: `$request->context()->authorizer()`, a `Lomkit\Rest\Authorization\Authorizer`.

Within a request:

- Each distinct check runs once. A check is an ability on a resource for a model, or for a model class, plus the related model of an attach or detach check. The policy runs on the first check, and the later checks of the request reuse its decision.
- `GateEvaluated` listeners and `Gate::after()` callbacks see one evaluation per distinct check per request, not one per call.
- After each write the package performs (mutate creates and updates, relation writes, destroy, restore and force destroy), the decisions on the written model are dropped, so a later check in the same request sees the new state. If a hook of yours writes a model and a later check in the request depends on it, call `$request->context()->authorizer()->forget($model)` after the write.
- Policies run with `Gate::forUser($request->user())`, or with the authenticated user when the request has none. `Gate::before()` and `Gate::after()` callbacks receive that user.
- The authorizer belongs to one user. When the user or the default guard changes, for example in a test or a long-running process that reuses a resource, the next check starts a new authorizer for the current user, and the decisions of the previous user are not reused.

Checks before validation:

- Each request checks the abilities of its endpoint, then the resources its payload reaches, before it validates the payload and before any query. A denied check answers with the status of the policy's response, `403` by default, even when the payload is invalid: the `403` now comes before the `422`. This also applies to Precognition requests, with or without `Precognition-Validate-Only`, which answered `204` or `422` before.
- The `authorize()` method of a request still runs first. When it passes, the endpoint abilities and the reached resources must pass too.
- The endpoint abilities are `viewAny` for `details` and `search`, and none for `mutate`, `destroy`, `restore` and `forceDestroy`, which check each row and each creation. `operate` is described under "Actions" below.
- To change them for one resource, override `abilities(Endpoint $endpoint, RestRequest $request): array` on the resource (`Lomkit\Rest\Http\Routing\Endpoint`), for example to require `deleteAny` on `Endpoint::Destroy`. The list replaces the default of the endpoint: a resource that returns `['export']` for `Endpoint::Search` no longer checks `viewAny` there, and its search runs without it. To add an ability, keep `viewAny` in the list, as in `['viewAny', 'export']`. `Endpoint::Operate` is the exception: its list adds to `viewAny`. A resource that already defines a method named `abilities()` must give it this signature.
- `details` checks `viewAny` before the controller's `beforeDetails()` hook runs, so the hook no longer runs for a user who cannot list the resource.
- Every resource the payload reaches needs `viewAny` on that resource: each level of an include (`team.replies` needs `viewAny` on the teams and on the replies), each level of an aggregate, each level of a filter on a relation, nested filters included, the relation of a relation group, and a filter on a pivot column (`tags.pivot.position` needs `viewAny` on the tags). The same applies to the `search` of an action. A filter on a relation did not check the related resource before, and an include was checked only when the query returned rows.
- Each `create` in `mutate`, at the root or under a relation, needs `create` on the created resource. It is checked once per resource class and request, before validation, with the model class as its argument, as `Gate::inspect('create', Model::class)` does. Before, it was checked for each new model, after validation, with an unsaved model instance: a `create` policy method that requires a model as its second parameter no longer receives one. A creation under a relation that the package refuses, such as a `HasManyThrough` relation or a relation marked `prohibitedOnCreation()`, is checked before the `422` as well.

Actions:

- An unknown action answers `404` to a user allowed `viewAny` on the resource, and `403` to the others.
- A known action checks, before validation and in this order: `viewAny` on the resource, the abilities that the resource's `abilities()` returns for `Endpoint::Operate` (none by default), then the abilities that the action's `abilities()` returns. These abilities add to `viewAny` and never replace it.
- `Action::abilities(): array` returns `['viewAny']` by default, for every action, standalone and targeted actions included. An action that returns `['export']` checks `viewAny`, then `export`; listing `viewAny` as well, as in `['viewAny', 'export']`, checks the same abilities. An action that already defines a method named `abilities()` must give it this signature.
- An action whose `abilities()` returns `[]` runs without any of these checks: neither `viewAny` nor the resource's abilities for `Endpoint::Operate`, and the search of its models does not check `viewAny` on the resource either. Use it for an action that any user may run, guests included. The resources that its `search` reaches through includes, aggregates and filters on relations still need `viewAny`.

Denials:

- A check made before validation (an endpoint ability or a reached resource) that is denied throws `Lomkit\Rest\Exceptions\AuthorizationDenied`, which extends Laravel's `Illuminate\Auth\Access\AuthorizationException`. It holds every denial of these checks, at most 50, as `Lomkit\Rest\Authorization\Denial` objects in `$exception->denials`, each with the path in the payload (`search.includes.0`, `mutate.1.relations.team`, or an empty string for the endpoint), the ability, the resource class, the key of the model when the check is on a model, and the message.
- Its message, code and status are those of the first denial, in the order of the request's rules. The response body stays `{"message": ...}`, and a policy that returns `Response::denyAsNotFound()` still answers `404`.
- The checks on rows (`view`, `update`, `delete`, `restore`, `forceDelete`, and the attach and detach checks of a relation) still throw Laravel's `AuthorizationException`, with no denials, as before.
- To add the denials to the JSON body, register the renderer in `bootstrap/app.php`. It adds a `denials` list of `{path, ability, key, message}`, where `key` only appears for a model, to every `AuthorizationDenied` except a `404`, and leaves the other responses unchanged:

  ```php
  ->withExceptions(function (Exceptions $exceptions): void {
      $exceptions->render(new \Lomkit\Rest\Exceptions\DenialsRenderer);
  })
  ```

Checks the request did not plan:

- The queries still check `viewAny` on each resource they search, and `create` on each resource they create. A check that the request already made before validation is reused; another one is evaluated when the query runs.
- The resource of the endpoint is the exception: its endpoint abilities stand in for `viewAny`. When they leave `viewAny` out (by default for `mutate`, `destroy`, `restore` and `forceDestroy`, through an override of the resource's `abilities()`, or for an action whose `abilities()` returns `[]`), the queries do not check it on that resource, and strict mode does not report it. The other resources the queries search are checked, or reported, as above.
- With `authorizations.strict` set to `true` (`false` by default, see [Configuration](#configuration)), a check that the request did not make before validation throws `Lomkit\Rest\Exceptions\UnplannedCheckException` instead. Turn it on in your test suite to find a resource that the request reaches without checking it first.
- A `Lomkit\Rest\Query\Builder` used outside a request evaluates `viewAny` when it searches. In strict mode, plan it first: `$resource->context()->authorizer()->plan([Check::onClass($resource, 'viewAny')])` (`Lomkit\Rest\Authorization\Check`).

Cache:

- The cache is off by default, see [Configuration](#configuration).
- When the cache is on, a decision is stored as an array of scalars (`allowed`, `message`, `code` and `status`), not as a `Response` object, so it reads back with any store, including the `file` and `database` stores with `cache.serializable_classes` set to `false`.
- The cache key holds the guard, the user class and key, the resource class, the ability, and the class and key of each model. Entries written under the previous keys are not read anymore and expire on their own.
- A model whose key is cast to an enum is keyed by the value of the enum. Checks on a model that is not saved yet, or whose key is not a string, an integer or an enum, are neither cached nor shared with another check of the request.
- `create` checks are not cached: they run once per request, including the `authorized_to_create` gate.
- A cached decision stays for `authorizations.cache.default` minutes, or the duration that `cacheAuthorizationFor()` returns: a change to a policy, to the user or to the model shows once the entry expires.
- `Resource::getAuthorizationCacheKey()` is removed: the package builds the key.
- `isAuthorizationCacheEnabled()` and the `DisableAuthorizationsCache` trait turn the cache off for one resource. When `authorizations.cache.enabled` is `false`, no resource uses the cache.

Resource methods:

- The authorization methods declare native types. An override must declare compatible types:
  - `authorizeTo(string $ability, Model|string $model): void` and `authorizedTo(string $ability, Model|string $model): Response`;
  - `authorizeToAttach(Model $model, Model $toAttachModel): void`, `authorizedToAttach(Model $model, Model $toAttachModel): bool`, and the same for `authorizeToDetach()` and `authorizedToDetach()`;
  - `authorizeToPerformActionOnRelationship(string $ability, Model $model, Model $toActionModel): void` and `authorizedToPerformActionOnRelationship(string $ability, Model $model, Model $toActionModel): bool`.
- `authorizeTo()` calls `authorizedTo()`, so an override of `authorizedTo()` applies to the endpoints as well as to the gates. The checks made before validation, on the endpoint and on each reached resource, also go through the `authorizedTo()` of their resource.
- `authorizedTo()` returns a new `Illuminate\Auth\Access\Response` built from the decision, with or without the cache. It keeps the result, the message and the status of the policy's response, and its code when the code is an integer or a string. It does not keep a code of another type, such as an enum or an object, or an instance of your own `Response` subclass.

### Selects, sorts and aggregates

A search reads each field through its declaration: `writeOnly()`, `selectable(false)` and `virtual()` decide what a row can return, and `sortable()` and `sortUsing()` decide how the field is sorted.

Selects and rows:

- `selects` accepts the selectable fields only. A field declared `writeOnly()`, `selectable(false)` or `virtual()` gives a 422 under `search.selects.<i>.field`, as an undeclared field does. The `selects` of an include are checked against the fields of the related resource.
- Without `selects`, a row holds the selectable fields of its resource only, at every level of the includes. A `writeOnly()` field is never returned, by any endpoint: declare `writeOnly()` on a field that clients may write but not read. A `virtual()` field is not returned either.
- `destroy`, `restore` and `forceDestroy` refuse `search`: an empty array is accepted, and any other `search` gives a 422 under `search`, "The search field is prohibited." for a value that is not empty and "The search field must be an array." for `null` or a blank string. No row is deleted or restored then. Their response holds the selectable fields of each row, without gates or aggregates.
- Virtual fields cannot be selected yet, and `requires()` has no effect yet.

Sorts:

- `sorts` accepts the sortable fields only. A `Json` field, a field declared `sortable(false)` or `writeOnly()`, and a `virtual()` field without `sortUsing()` give a 422 under `search.sorts.<i>.field`. A field declared `selectable(false)` can still be sorted. A text search that runs on a search engine sorts on the `sortable()` attributes of its `ScoutEngine`, see [Search engines](#search-engines).
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
- `Lomkit\Rest\Query\Builder::sort($field, $direction)` is removed. `applySorts($sorts, ?ResolvedText $text = null)` applies each sort with a `Lomkit\Rest\Query\Clauses\SortClause`, which calls `Field::sort()`, and takes the text search of the request as a second parameter: an override must declare it, see [Text search](#text-search). A sort passed to `search()` or `applySorts()` must name a declared, sortable field of the resource, or `@relevance`: another field throws `Lomkit\Rest\Exceptions\InvalidFieldException`. `@relevance` orders by relevance (`Lomkit\Rest\Query\Clauses\RelevanceClause`) when the search has a text and a strategy that orders by relevance, and throws `InvalidFieldException` otherwise.
- `defaultOrderBy()` still orders by the columns it names. The default `Resource::defaultOrderBy()` returns the key of the model in descending order, `[static::newModel()->getKeyName() => 'desc']`, instead of `['id' => 'desc']`: a search without `sorts` on a model whose key is not `id`, such as `code`, now works instead of failing on an unknown `id` column.

Aggregates:

- `type` accepts the values of `Lomkit\Rest\Fields\AggregateFunction`: `count`, `min`, `max`, `avg`, `sum` and `exists`. When `type` is missing or unknown, only `type` gets an error.
- `min` and `max` accept a field of the related resource that is sortable by its own column (`Field::isSortableByColumn()`): sortable by default or declared `sortable()`, and neither `virtual()` nor `writeOnly()`. `sortUsing()` does not count, because the aggregate reads the column. A `Json` field that is not declared `sortable()`, a field declared `sortable(false)`, `virtual()` or `writeOnly()`, and an undeclared field give a 422 under `search.aggregates.<i>.field`, with or without `sortUsing()`.
- `min` and `max` refuse a `Boolean` field, and an app type that extends `Boolean`, with the same 422, on every database.
- `min` and `max` accept an `ID` field with UUID keys (a model using `HasUuids`, or `keyType('uuid')`) on every database, and return the same string on each of them. On PostgreSQL the aggregate reads the key as text, which keeps the order of the UUIDs.
- `avg` and `sum` also need a `Number` field: `Number`, `Integer`, or an app type that extends `Number`. Another field, such as a `Text`, `Date`, `Boolean` or `ID` field, or an app type that extends `Field` directly, gives a 422. To average or sum the values of an app type, make it extend `Number`.
- `count` and `exists` still refuse `field`. A direct call to `Lomkit\Rest\Query\Builder::aggregate()` with `count` or `exists` ignores `field`.
- `aggregate()` keeps its signature and applies each aggregate with a `Lomkit\Rest\Query\Clauses\AggregateClause`. With `min`, `max`, `avg` or `sum`, the `field` passed to `search()` or `aggregate()` must name a declared field of the related resource: another field throws `Lomkit\Rest\Exceptions\InvalidFieldException`. The response key does not change: the `alias`, or `<relation>_<type>_<field>`.

Include aliases:

- An include `alias` is still checked against the name of every field of the resource, `writeOnly()` and `virtual()` fields included. Its messages start with the key instead of `The alias`, for example `The search.includes.0.alias conflicts with a field of this resource.`
- The classes of `Lomkit\Rest\Rules\Search`, `SearchIncludeAlias` included, and `Lomkit\Rest\Rules\Concerns\ReachesRelations` are removed. A search can no longer be built from rule nodes, and `Search::nested()` is gone. The search payload is read by the package's search parser, see [Validation engine](#validation-engine).

Scramble:

- The search body documents the sortable fields under `sorts` and the selectable fields under `selects`, with the values of `SortDirection` and `AggregateFunction`.

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

- `filters` accepts the filterable fields only. A `Json` field, a field declared `filterable(false)`, and a `virtual()` or `writeOnly()` field without `filterUsing()` give a 422 under `search.filters.<i>.field`, as an undeclared field does. The message changes for both: `The selected search.filters.0.field is invalid.` instead of `The 'field' field is not valid.`
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
  - `like`, `not like`, `ilike` and `not ilike` need a string.
  - `in` and `not in` need a list of at least one value, and `between` and `not between` a list of exactly two values, each of the field's type.
  - `is null` and `is not null` refuse a value.
- `=` and `!=` with `null` give a 422 under `search.filters.<i>.value`. Before, `=` with `null` matched the rows without a value, and `!=` with `null` the rows with one. Use `is null` or `is not null` on a nullable field instead: `{"field": "summary", "operator": "is null"}`.
- A `Date` value is compared as the start of its day, and a `Date` or `DateTime` value is written in the date format of the model before the comparison.

Relations:

- A filter on a relation path (`author.first_name`, `author.tags.name`) matches the rows that have a related row matching the filter, as before, and applies the `searchQuery()` of the resource at the end of the path to that related row. This `searchQuery()` now runs in its own group: an `orWhere()` in it no longer makes the filter match related rows outside the filter.
- `type` and `nested` work the same on relation paths and on fields: a filter with the `or` type is combined with the previous one by `or`, and the filters of a `nested` group stay inside that group.
- A filter through a `MorphTo` relation, such as `labelable.title`, reads the field on the resource that the relation declares, and only matches the rows whose morph type is the model of that resource and that its `searchQuery()` keeps. Rows of another type never match, and a stored type that is not a model class no longer gives an error. The relation can be anywhere in the path, as in `labels.labelable.title` or `labelable.loan.borrower`, and the `searchQuery()` of the resource it declares applies there too, in its own group.
- An include or an aggregate whose `relation` ends with a `pivot` segment, such as `tags.pivot`, gives a 422 under its `relation` key. Before, it passed validation and failed when the query ran. `Resource::relation('tags.pivot')` returns `null`.

Relation groups:

- A filter that holds `relation` is a relation group. `{"relation": "posts", "exists": false, "nested": [{"field": "status", "value": "draft"}]}` keeps the records that have no post in draft. Its keys are `relation`, `exists`, `type` and `nested`.
- With `exists` `true`, the default, the group keeps the records that have at least one related record that matches every filter of `nested`. With `exists` `false`, it keeps the records that have no such related record. Without `nested`, or with an empty list, any related record counts.
- A group applies all its filters to the same related record. `{"relation": "posts", "nested": [{"field": "status", "value": "published"}, {"field": "featured", "value": true}]}` keeps the records that have one post that is both published and featured. Two dotted filters, `posts.status` and `posts.featured`, can match two different posts. A dotted filter keeps its meaning: `{"field": "posts.status", "operator": "!=", "value": "draft"}` keeps the records that have a post that is not a draft.
- The filters of `nested` take the fields of the related resource, with their operators and values, `type`, groups, relation paths and relation groups. The `searchQuery()` of the related resource applies to the related records, in its own group. `type` combines the group with the previous filter, as for any filter.
- `relation` names one relation of the resource. Another name, or a dotted path such as `posts.comments`, gives "The selected search.filters.0.relation is invalid.". A `null` `relation` gives "The search.filters.0.relation field is required.", and a value that is not a string also gives "The search.filters.0.relation field must be a string.". To filter on a relation of the related resource, use a relation path in `nested`, such as `{"relation": "posts", "nested": [{"field": "comments.body", "value": "Thanks"}]}`. A relation group in `nested` holds filters when `rest.limits.depths.filters` is 2 or more.
- `exists` is `true` or `false`. Another value, `1`, `0` and `null` included, gives "The search.filters.0.exists field must be true or false.".
- `field`, `operator`, `value` or another key next to `relation` gives a 422 on that key: "The search.filters.0.field field is prohibited.". `exists` on a filter without `relation` gives the same 422.
- Through a `MorphTo` relation, the group reads the fields of the resource that the relation declares. It matches the rows whose morph type is the model of that resource. With `exists` `false`, it keeps the rows of another type, of a type that is not a model and without a type. `types` gives a 422.
- On a `BelongsToMany`, `MorphToMany` or `MorphedByMany` relation, `nested` takes the fields of the related resource. A pivot field there, such as `pivot.position`, gives a 422. Filter it with a path from the resource that declares the relation, such as `tags.pivot.position`.
- The filters of includes and aggregates and the search of an action accept relation groups. A text search run by a search engine does not, see [Search engines](#search-engines).

Text search:

- With `Contains` and `FullText`, `search.filters` follow the rules above next to the text. A text search that runs on a search engine accepts the filters of its `ScoutEngine` declaration, see [Search engines](#search-engines). The filters of includes and aggregates always follow the rules above.

Database differences:

- `like` and `not like` follow the database: they are case-sensitive on PostgreSQL, follow the collation of the column on MySQL (case-insensitive on the default `_ci` collations), and ignore the case of ASCII letters only on SQLite.
- `ilike` and `not ilike` ignore case. PostgreSQL runs `ilike`, and MySQL and SQLite run `like`: on MySQL, `ilike` stays case-sensitive on a column with a binary or case-sensitive collation, and on SQLite it ignores the case of ASCII letters only.

Query builder and custom relations:

- `Lomkit\Rest\Query\Builder::filter()` is removed. `applyFilters(array $filters)` keeps its signature and applies the filters as one group of clauses (`Lomkit\Rest\Query\Clauses\FilterGroupClause`, with a `FieldConditionClause`, a `RelationConditionClause` or a `RelationGroupClause` per filter), which call `Field::filter()`. A filter passed to `search()` or `applyFilters()` must name a filterable field, or a path to one, with an operator of that field: another field or operator throws `Lomkit\Rest\Exceptions\InvalidFieldException`. A filter that holds `relation` is applied as a relation group: `has()` on the Eloquent relation of that name, with `<` when `exists` is `false`. On a `MorphTo`, it uses `hasMorph()` on the model of the declared resource, inside `whereNot()` when `exists` is `false`, and the rows without a type are kept. Only `false` in `exists` means absence there. A name that is not a relation of the resource, or a dotted path, throws `InvalidFieldException`: "Unknown relation [comments]."
- `Relation::filter()` and the `Lomkit\Rest\Contracts\RelationResource` interface are removed. Remove `implements RelationResource` from your custom relations: an override of `filter()` is no longer called, and a filter through the relation uses `has()` on the Eloquent relation of the same name.
- `Resource::isNestedField()`, the `Lomkit\Rest\Rules\Resource\ResourceFieldOrNested` rule and the `relation_without_pivot()` helper are removed, with no public replacement: filters, includes and aggregates validate their dotted paths themselves.
- The package no longer autoloads a file of global functions.

Scramble:

- The `filters` of the search body list the filterable fields of the resource under `field`, without the dotted paths, and the operators of `FilterOperator` under `operator`.

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
- The model is filled with the writable fields of the operation only, field by field, after the `mutating()` hook.

Rules:

- The rules of each writable field validate its attribute: the base rule of its type, `nullable`, `rules()`, and `creationRules()` on `create` or `updateRules()` on `update`, `sync` and `toggle`. The base rule now applies to mutations: `null` is refused unless the field is `nullable()`, and an array is refused except on a `Json` field. For example, a `Text` field needs a string, an `Integer` field an integer, an `Enum` field a value of its enum, and a `Date` or `DateTime` field a date.
- The resource rules still apply, for rules that compare attributes and for nested keys such as `metadata.source`. Each key gets one list: the field rules, then `createRules()` or `updateRules()`, then `rules()`. Pipe strings are split before the merge, so `'string|min:3'` in `rules()` and `'required'` in `createRules()` give `['required', 'string', 'min:3']`. Before, the resource rules were merged with `array_merge_recursive()`, which left a pipe string unsplit next to another rule of the same key. `rules()`, `createRules()` and `updateRules()` are read once per request, and the entries share the rule objects they return, see "Custom rules" in [Validation engine](#validation-engine).
- A rule that names another attribute, such as `required_if:featured,true` or `after_or_equal:published_on`, compares with that attribute in the same entry, `mutate.0.attributes.featured`, when the resource declares a field of that name. Before, it read the key of that name at the root of the payload. A `Rule::when()` condition receives the attributes of its entry. A rule that depends on the record of the entry, such as `Rule::unique()->ignore()`, is built in `Contextual::rule()`, see "Custom rules" in [Validation engine](#validation-engine).

Nested writes:

- The keys that the parent relation sets win over the attributes of the same name. On `HasOne`, `HasOneOfMany`, `HasMany`, `MorphOne`, `MorphOneOfMany` and `MorphMany`, the entry gets the key of the parent, and on the morph relations its morph type, also when the entry sends its own `BelongsTo` or `MorphTo` relation to another parent. On `BelongsTo` and `MorphTo`, the foreign key and the morph type that the relation sets win over the attributes of the entry. They are read after the `mutating()` hook, so a key that the hook sets on such a relation, for example with `associate()`, is the one written. Before, an attribute such as `category_id` replaced them.
- `MorphOne`, `MorphOneOfMany` and `MorphMany` write the morph type with `getMorphClass()`: with a morph map, they write the alias instead of the class name, as `MorphTo` does.

Custom relations and query builders:

- `Lomkit\Rest\Fields\MutationOperation` lists the operations: `create`, `update`, `attach`, `detach`, `sync` and `toggle`. `Relation::mutationOperations()` returns a list of its cases instead of strings, for example `[MutationOperation::Create, MutationOperation::Attach]`.
- `mutateModel(Model $model, array $attributes, array $mutation, array $owned = [])` takes a fourth parameter, which an override must declare too. `$attributes` holds the attributes of the entry and is written through the writable fields: a key that is not a writable field is not written. `$owned` holds the keys that relations set, written last.
- In `applyMutation($mutation, $attributes, $key)` and `mutations($mutation, $attributes)`, `$attributes` holds the keys that the parent relation sets. They are written after the attributes of the entry.
- The mutation methods of `Lomkit\Rest\Query\Builder` declare native types, and the `EloquentQueryBuilder` contract declares the same: `mutate(array $parameters = []): array`, `applyMutation(array $mutation = [], array $attributes = [], int|string|null $key = null): Model`, `mutations(array $mutation = [], array $attributes = []): array` and `mutateModel(Model $model, array $attributes, array $mutation, array $owned = []): Model`; `mutationAbility(array $mutation): string` is protected and `$mutateOperationsVerbose` is a typed `array`. An override or an implementation of the contract must declare compatible types. The mutate endpoint passes to `mutate()` the entries of `MutateRequest::payload()`, rebuilt as arrays, see "Mutate payload" in [Validation engine](#validation-engine).
- A relation that sets keys of the model in `beforeMutating()` returns them from `ownedAttributes(Model $model): array`, as `BelongsTo` and `MorphTo` do, so that they are written after the attributes. It is called after the `mutating()` hook, and the keys of the parent relation win over the keys it returns. The default returns `[]`.

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
- A pivot field is a column of the pivot table, and a pivot value is written to its column as sent, encoded first by a `Json` pivot field as described under [Typed fields](#typed-fields). A pivot field declared `virtual()`, or with `fillUsing()`, throws `Lomkit\Rest\Exceptions\InvalidFieldException` when `pivotFields()` is called.
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
- Filters on `pivot` keys, such as `tags.pivot.weight`, accept the filterable pivot fields only, with the operators and values of their type, as described under Filters. A pivot field declared `writeOnly()` or `filterable(false)` gives a 422 under `search.filters.<i>.field`.

Scramble:

- The relations are read from the relation objects that `relations()` returns. A field declared in `pivotFields()` is no longer documented as a relation. A custom relation is documented with its class name, the operations of its `mutationOperations()`, as a list when it has many entries, and with its pivot when it implements `PivotRelation`.
- The `pivot` of a relation entry lists the writable pivot fields with their rules. Its description names the fields writable on each operation: `create` and `attach`, then `update`, `sync` and `toggle`.

### Details

The details endpoint, `GET /{resource}`, describes the resource with typed entries. Its keys are `actions`, `instructions`, `fields`, `limits`, `scopes`, `relations`, `rules` and `text`. An excerpt:

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
  "text": {"fields": ["title"], "modes": ["words", "phrase"], "relevance": false, "trashed": false, "max_length": 255, "engine": null}
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
- `MorphTo` entries add `types` and `morph`. `types` maps each morph type to its resource class. The morph type is the morph-map alias of the model, or its class without an alias. Read the targets of a `MorphTo` from `types`. `morph` holds the `type_column` and the `key_column` of the relation.
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

Behaviour:

- Empty maps are `{}`: the `meta` of every entry, input entries, actions, instructions and scopes included, and the maps of `rules`. Lists stay `[]`.
- Details builds every field entry, the pivot fields and the text search, so it now throws where it listed names:
  - `Lomkit\Rest\Exceptions\InvalidTextSearchException` for a text search declaration error, as on the first search;
  - `Lomkit\Rest\Exceptions\InvalidResourceException` for a `MorphTo` whose model method does not return a `MorphTo` relation;
  - `Lomkit\Rest\Exceptions\InvalidFieldException` for an `Enum` field or pivot field with no enum class and no enum cast;
  - `BadMethodCallException` for a many-to-many relation or a `MorphTo` whose model method is missing.
- Details checks `viewAny` only and applies no policy to the entries. It lists what the declarations of the resource return for the current request, which can depend on `$request->user()`.
- Clients ignore the keys they do not know. Later releases can add keys.

Scramble:

- Scramble documents the details response: the field entries, the relations with `pivot_fields`, `types` and `morph`, the rules, `text`, and the input entries of actions, instructions and scopes.

### Framework integration

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

- They import the classes they use and mark each overriding method with `#[\Override]`. A model or a resource whose short name the file already uses is imported under an alias, for example `use App\Models\Resource as ResourceModel;`. In a resource, the reserved short names are the resource itself, `Resource`, `RestRequest`, `Rule`, the field classes and the relation classes, so a model named `Rule` or `Text` is imported as `RuleModel` or `TextModel`.
- `rest:action` and `rest:instruction` generate a `fields()` method that returns `[]`. Before, it declared a required `id` field: the instruction refused every request without it, and the action ignored it and ran on every model of the search.
- `rest:base-resource` generates an empty abstract class. Before, it declared the query hooks, which returned `$query` unchanged. Override a hook in your base resource when you add constraints to it.
- `rest:base-controller` generates an empty abstract class.
- `rest:response` generates a `map()` method with its array types in PHPDoc.

Options:

- `rest:resource`, `rest:controller`, `rest:policy`, `rest:action`, `rest:instruction`, `rest:response`, `rest:base-resource` and `rest:base-controller` accept `--force` (`-f`), which replaces an existing class. Without it, an existing class is kept: the command prints `<Type> already exists.` and exits with 0.
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
- A `MorphTo` relation, or a relation of another class, is not generated: the command prints `Skipped: commentable: MorphTo relations are not generated, declare it in relations() by hand.`
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

- `rest:policy` is a new generator. `php artisan rest:policy PostPolicy --model=Post` writes `app/Policies/PostPolicy.php`, a read-only policy: `viewAny()` and `view()` return `true` for a signed-in user, and `create()`, `update()` and `delete()` return `false`. For a model that uses `SoftDeletes`, `restore()` and `forceDelete()` are added and return `false`. Edit the policy before clients need to write.
- The user parameter has the type of the model of the user provider of the default guard (`auth.providers.<provider>.model`), or `App\Models\User` when it is not set. In the policy of the user model itself, the second parameter is named `$model`.
- Without `--model`, the model is guessed from the class name: `PostPolicy` gives `App\Models\Post`, `Admin/InvoicePolicy` gives `App\Models\Admin\Invoice`, and `LearningResourcePolicy` gives `App\Models\LearningResource`. When the command runs interactively, it asks for the model. When no model is given or guessed, or the model does not exist, the command stops with exit code 1 before any file is written.
- When Gate already finds another policy for the model, through `Gate::policy()`, `#[UsePolicy]` or the naming convention, the command prints `The model [App\Models\Post] already has the policy [App\Policies\ContentPolicy].`, writes nothing and exits with 0. With `--force`, it writes the policy and warns that Gate still uses the other one: register the new policy with `Gate::policy()` to use it.

Published stubs:

- The generators read the stubs that `php artisan stub:publish` copies to `stubs/rest.<name>.stub`, and fall back to the package stubs. Before, they ignored them. The stubs published under 2.x declare untyped properties such as `public static $model`, which are a fatal error in 3.0 (see [Native types](#native-types)): delete your `stubs/rest.*.stub` files, or publish them again with `php artisan stub:publish --force` after you have saved the Laravel stubs that you customised.
- The `stubs/rest/*.stub` files that versions before 2.14 used are not read.
- A published stub keeps the placeholders that the generator fills: `{{ namespace }}` and `{{ class }}` in each stub, `{{ namespacedModel }}`, `{{ model }}`, `{{ imports }}`, `{{ fields }}` and `{{ relations }}` in `rest.resource.stub`, `{{ namespacedResource }}` and `{{ resource }}` in `rest.controller.stub`, and `{{ namespacedModel }}`, `{{ namespacedUserModel }}`, `{{ model }}`, `{{ modelVariable }}`, `{{ user }}` and `{{ softDeleteMethods }}` in `rest.policy.stub`. Otherwise the command throws `Lomkit\Rest\Exceptions\InvalidStubException`, which names the stub and the missing placeholder.

### Quick start

`rest:quick-start` exposes the models that you choose, behind authentication, and checks the result before it reports success:

```bash
php artisan rest:quick-start
php artisan rest:quick-start --no-interaction --model=Post --model=Tag --install-api
php artisan rest:quick-start --no-interaction --model=Post --guard=api --install-api
```

- The command asks `Which models should the API expose?` and offers the models of `app/Models`, or of `app` when that directory does not exist, with none selected. With `--no-interaction`, or when no terminal can answer, pass each model with `--model`: without it, the command stops with exit code 1 and writes nothing. Before, it always exposed `App\Models\User`.
- Each model must exist and have a table, and two models whose routes the command adds must not get the same resource name, such as `App\Models\Note` and `App\Models\Archive\Note`, which would both be `notes`. Otherwise the command stops with exit code 1 before any file is written, and names the models.
- For each model, it writes the resource with the fields and relations of the table, as `rest:resource --generate` does, the controller, and a read-only policy, as `rest:policy` does, when Gate finds no policy for the model. It creates the base resource and the base controller when they are missing. The relations between the models of one run are generated. A relation to a model that is not exposed is skipped and reported: `Skipped: Post.author: no resource for App\Models\User. Rerun with --model=User to expose users.`
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
- The command never edits the user model. When the user model does not use the `HasApiTokens` trait of the package of the guard, `Laravel\Sanctum\HasApiTokens` or `Laravel\Passport\HasApiTokens`, it prints a warning. It ends with a command that creates a token, with `plainTextToken` for Laravel Sanctum or `accessToken` for Laravel Passport, a `curl` request to the search route that the application registered for the first model, with its API prefix, and, when it wrote policies, a reminder that they refuse every write. With another guard, it prints no warning and no token command: it says `Authenticate with the <guard> guard to call the API.`, for example `Authenticate with the web guard to call the API.` with `--guard=web`, and the `curl` request has no `Authorization` header. When the run adds no route, the warning and the token command follow the guard that the existing routes of the models name in `->middleware('auth:<guard>')`: `--guard` only sets the guard of the routes to add. When the command finds no such guard, for example for routes in a group, it uses `sanctum`.
- The generated classes use the root namespace of the application, such as `MyApp\`. Before, the command replaced `App\` in the generated files, which gave `MyMyApp\`.
