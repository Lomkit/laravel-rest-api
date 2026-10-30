---
name: laravel-rest-api
description: Use this skill when working with the `lomkit/laravel-rest-api` package — building or modifying Resources, Controllers, Actions, Instructions, registering REST routes via `Rest::resource()`, or writing client payloads for the `search`, `mutate`, `operate`, `details`, `destroy`, `restore` and `force` endpoints. Triggers include the `Lomkit\Rest\` namespace, the `Rest` facade, the `rest:*` artisan commands, and `app/Rest/Resources` / `app/Rest/Controllers` directories.
---

# Laravel Rest API (lomkit/laravel-rest-api)

`lomkit/laravel-rest-api` exposes Eloquent models through a small, fixed set of POST-driven endpoints. Instead of writing one controller per use case, you declare a **Resource** (what is exposed and how it can be queried/mutated) and a thin **Controller** that binds the resource to a route prefix. The package wires CRUD, search, batch mutations, custom actions, soft-delete handling, policy-based authorization and OpenAPI documentation on top of that pair.

Requirements: PHP 8.3+, Laravel 13.30+.

Official documentation: https://laravel-rest-api.lomkit.com/

## When to use this skill

Use this skill when working with the `lomkit/laravel-rest-api` package — building or modifying Resources, Controllers, Actions, Instructions, registering REST routes via `Rest::resource()`, or writing client payloads for the `search`, `mutate`, `operate`, `details`, `destroy`, `restore` and `force` endpoints. Triggers include the `Lomkit\Rest\` namespace, the `Rest` facade, the `rest:*` artisan commands, and `app/Rest/Resources` / `app/Rest/Controllers` directories.

## Features

- **Resource**: declares the shape of an Eloquent model's API (fields, relations, scopes, validation, lifecycle hooks). Generate with:

  ```bash
  php artisan rest:resource UserResource --model=User
  ```

- **Controller**: a thin binding between a route prefix and a Resource, exposing request-level hooks (`beforeSearch`, `afterMutate`, ...). Generate with:

  ```bash
  php artisan rest:controller UsersController --resource=UserResource
  ```

- **Routing**: register all REST endpoints (`details`, `search`, `mutate`, `operate`, `destroy`, optional `restore`/`force`) for a model with a single call:

  ```php
  Rest::resource('users', \App\Rest\Controllers\UsersController::class);
  ```

- **Search**: filterable, sortable, paginated reads with nested relation includes, aggregates, scopes, and per-row policy gates via `POST /{resource}/search`.
- **Mutate**: batched create/update plus relation operations (`attach`, `detach`, `sync`, `toggle`) in one request via `POST /{resource}/mutate`.
- **Actions**: custom mutators exposed under `/{resource}/actions/{uriKey}`, runnable against a `search`-resolved set of models, against an explicit list of ids, or standalone. Generate with:

  ```bash
  php artisan rest:action SendWelcomeNotificationAction
  ```

- **Instructions**: custom query refinements invoked from the `search` payload's `instructions` key. Generate with:

  ```bash
  php artisan rest:instruction OddEvenIdInstruction
  ```

- **Soft-delete endpoints**: opt-in `restore` and `force` routes via `->withSoftDeletes()` chained on `Rest::resource(...)`.
- **Authorization**: automatic per-model Policy checks (`viewAny`, `view`, `create`, `update`, `delete`, `restore`, `forceDelete`, `attach{Model}`, `detach{Model}`) plus client-requested per-row `gates`. Class-level checks (`viewAny` of every reached resource, `create`) run before validation, so a denied check answers `403` before any `422`.
- **Custom Response**: override the output shape per Resource via `public static string $response = UserResponse::class;`. Generate with:

  ```bash
  php artisan rest:response UserResponse
  ```

- **OpenAPI documentation**: native Scramble extension (`LomkitLaravelRestApiOperationExtension`) infers docs from Resources.
- **Precognition**: live form validation (`Precognition: true` header) short-circuits the request after validation when `rest.precognition.enabled = true`.

## If the package is installed

- **Default to extending it** for new endpoints — generate a `Resource` + `Controller` and register with `Rest::resource(...)` rather than adding a plain Laravel controller for the same model.
- **Match existing patterns** in `app/Rest/` — base classes (`app/Rest/Resources/Resource.php`), naming, hook overrides, and the project's `searchQuery` / `mutateQuery` conventions usually carry tenant or visibility constraints that you must preserve.
- **Don't mix paradigms for the same model.** If `UserResource` is already exposed via this package, don't add `Route::apiResource('users', ...)` alongside it — the two authorization models will diverge.
- **Prefer Actions/Instructions over custom controller methods.** See "Working conventions and gotchas" below.

If the package is **not** installed and the user explicitly asks for it, install per "Installation & bootstrapping" below. Otherwise, don't push it on a project that hasn't opted in — recommend it only when the use case (rich filtering, batch mutations, schema introspection) clearly matches.

## When to reach for this package

- The app needs rich filterable/sortable list endpoints with nested relation loading.
- You want batch create/update/attach/detach/sync in a single request.
- You want custom mutators (`actions`) and query refiners (`instructions`) exposed in a uniform way.
- You want policies + gates surfaced automatically to the client.

## Installation & bootstrapping

```bash
composer require lomkit/laravel-rest-api
php artisan vendor:publish --tag="rest-config"   # publishes config/rest.php
php artisan rest:quick-start                     # optional: asks which models to expose, then writes their resources, controllers, read-only policies and auth:sanctum routes
```

`rest:quick-start` runs `php artisan install:api` (Laravel Sanctum) when `routes/api.php` is missing, after a confirmation or with `--install-api`. Without interaction, name the models: `php artisan rest:quick-start -n --model=Post --model=Tag --install-api`. A related model that is not chosen is skipped, with the `--model` option to add. Without a terminal (CI, scripts, agents), pass `--model` and `--install-api` too: the command never answers its own questions. It stops before any write when existing routes already use the URIs or names of the new ones (for example `Route::apiResource('posts', ...)`). The command keeps existing files, never edits the user model (add `HasApiTokens` yourself), checks the routes and policies in a fresh process and exits 1 when something blocks. The generated policies let signed-in users read and refuse every write: edit them before production.

The `RestServiceProvider` and `Rest` facade are auto-registered via composer's `extra.laravel`.

## Core concepts

### 1. Resource — `app/Rest/Resources/{Name}Resource.php`

A Resource declares the **shape of what the API exposes** for an Eloquent model. Generate one with:

```bash
php artisan rest:resource UserResource --model=User
php artisan rest:resource UserResource --model=User --generate   # one field per column of the users table, plus the relations that have a resource
```

Override these methods (all receive `Lomkit\Rest\Http\Requests\RestRequest $request`, so any list can be conditionally narrowed by user/permission). `fields()`, `relations()`, `scopes()`, `limits()`, `actions()`, `instructions()`, `scoutFields()` and `scoutInstructions()` run once per request, the first time the package reads them, and the resource keeps nothing between requests:

- `fields()` — list of typed `Lomkit\Rest\Fields\*` objects the client may select / filter / sort / mutate on: `ID`, `Text`, `Number`, `Integer`, `Boolean`, `Date`, `DateTime`, `Enum`, `Json`, or an app type extending `Field`. Strings and duplicate names throw `InvalidFieldException`. `getFields($request)` returns a `FieldSet`; `->names()` gives the list of names.

  ```php
  use Lomkit\Rest\Fields\{ID, Integer, Text};

  public function fields(RestRequest $request): array
  {
      return [ID::make('id'), Text::make('name'), Integer::make('price'), Text::make('note')->nullable()];
  }
  ```

  How a search reads a field depends on its declaration:

  ```php
  use Illuminate\Contracts\Database\Eloquent\Builder;

  ID::make('id')->sortable(false),                     // returned, 422 in sorts and aggregates
  Text::make('api_token')->writeOnly(),                // never returned, 422 in selects, sorts and aggregates
  DateTime::make('created_at')->selectable(false),     // not returned, 422 in selects, sortable
  Json::make('settings'),                              // returned, 422 in sorts, min/max/avg/sum
  Text::make('full_name')
      ->virtual()                                      // not returned, 422 in selects and aggregates
      ->sortUsing(fn (Builder $query, string $direction) => $query
          ->orderBy('last_name', $direction)
          ->orderBy('first_name', $direction)),
  ```

  - Selectable fields (not `writeOnly()`, not `selectable(false)`, not `virtual()`) are the only ones `selects` accepts and the ones a row returns without `selects`, at every include level. Virtual fields cannot be selected yet; `requires()` has no effect yet.
  - Sortable fields are the only ones `sorts` accepts: `Json`, `sortable(false)`, `writeOnly()` fields and `virtual()` fields without `sortUsing()` are refused. `sortUsing()` replaces the column order of its field (and makes a virtual or write-only field sortable); its callback receives the Eloquent query, on an include as well, and `asc` or `desc`. The other fields order by their qualified column (`posts.views`).

  How a mutation writes a field depends on its declaration too (see the `mutate` payload below):

  ```php
  use Illuminate\Database\Eloquent\Model;
  use Illuminate\Support\Str;

  ID::make('id'),                                      // refused on create and update
  ID::make('uuid')->creatable(),                       // a key the client sends on create
  Text::make('title')
      ->rules('max:255')
      ->creationRules('required')
      ->updateRules('filled'),
  Number::make('rating')->nullable(),                  // a number or null
  DateTime::make('created_at')->readonly(),            // refused on create and update
  Text::make('slug')->updatable(false),                // written on create only
  Text::make('full_name')
      ->virtual()                                      // written through its callback only
      ->fillUsing(function (Model $author, mixed $value): void {
          $author->forceFill(['first_name' => Str::before((string) $value, ' '), 'last_name' => Str::after((string) $value, ' ')]);
      }),
  ```
- `relations()` — array of `Lomkit\Rest\Relations\*` instances declared with `::make('relationName', TargetResource::class)`. Only declared relations are includable/mutatable.
- `scopes()` — `Lomkit\Rest\Scopes\Scope` objects naming the model's local scopes (`scopeX()` methods or `#[Scope]` methods) the client may invoke. Builder methods and macros such as `withTrashed` cannot be called as scopes. `fields()` declares the scope parameters, in the order of the scope method's arguments; strings throw `InvalidScopeException`.

  ```php
  use Lomkit\Rest\Fields\{Date, Integer};
  use Lomkit\Rest\Scopes\Scope;

  public function scopes(RestRequest $request): array
  {
      return [
          Scope::make('publishedSince')->fields([Date::make('since')]),           // scopePublishedSince(Builder $query, CarbonImmutable $since)
          Scope::make('popular')
              ->fields([Integer::make('min_views')->default(100)])
              ->meta(['label' => 'Popular']),
      ];
  }
  ```
- `limits()` — allowed pagination `limit` values (default `[10, 25, 50]`; `$defaultLimit` property is `50`).
- `actions()` — `Lomkit\Rest\Actions\Action` instances exposed under `/actions/{uriKey}`.
- `instructions()` — `Lomkit\Rest\Instructions\Instruction` instances exposed under the `instructions` search key.
- `rules(RestRequest $request): array`, `createRules(...): array`, `updateRules(...): array` — resource-level rules applied during mutate, for rules that compare attributes and for nested keys (`metadata.source`). Put the rules of one attribute on its field instead. Each key gets one list: the field rules, then `createRules()` (on `create`) or `updateRules()` (on `update`, `sync`, `toggle`), then `rules()`; pipe strings are split first.
- `defaultOrderBy()` — default sort (defaults to the model key in descending order, `[$model->getKeyName() => 'desc']`).
- Resource hooks (model-lifecycle): `mutating`, `mutated`, `destroying`, `destroyed`, `forceDestroying`, `forceDestroyed`, `restoring`, `restored`. Signatures: `mutating(MutateRequest $request, array $requestBody, Model $model): void`; the others take the endpoint's request and the model, e.g. `destroying(DestroyRequest $request, Model $model): void`.

For Scout-backed models (`Laravel\Scout\Searchable`), additionally:
- `scoutFields()`, `scoutInstructions()`, `searchScoutQuery(RestRequest $request, \Laravel\Scout\Builder $query): \Laravel\Scout\Builder`.
- A resource that overrides `searchQuery()` must declare `searchScoutQuery()` in the same class or in a subclass of it, with the same constraints written as `where()` conditions on attributes the index stores and can filter on (not a `query()` callback). Otherwise a text search throws `InvalidResourceException`. `searchQuery()` also applies to the page loaded after a text search.

The Resource also has typed static properties (PHP requires the `string` type when you redeclare them):
- `public static string $model = Post::class;` — the Eloquent class (required: a resource without it throws `InvalidResourceException`). Read it with `PostResource::modelClass()`.
- `public static string $response = \Lomkit\Rest\Http\Response::class;` — override with a subclass of `Lomkit\Rest\Http\Response` to customise output (see "Custom Response").

Resources extending `app/Rest/Resources/Resource.php` (generated via `rest:base-resource`) may override:
- `searchQuery`, `searchScoutQuery`, `mutateQuery`, `destroyQuery`, `restoreQuery`, `forceDestroyQuery` — apply baseline constraints (tenant scoping, soft-visibility, etc.) to `$query` itself and return it; the package ignores the returned builder:

  ```php
  use Illuminate\Contracts\Database\Eloquent\Builder;

  public function searchQuery(RestRequest $request, Builder $query): Builder
  {
      return $query->where('team_id', $request->user()->team_id);
  }
  ```
- `performDelete(RestRequest $request, Model $model): void`, `performRestore(...): void`, `performForceDelete(...): void` — override the actual operation on a model.

**Anything not declared in `fields()` / `relations()` / `scopes()` / `actions()` / `instructions()` is rejected.** This is the security model — don't bypass it.

### 2. Controller — `app/Rest/Controllers/{Name}sController.php`

```bash
php artisan rest:controller UsersController --resource=UserResource
```

Generated controllers are intentionally tiny:

```php
use App\Rest\Resources\UserResource;

class UsersController extends Controller
{
    public static string $resource = UserResource::class;
}
```

`$resource` keeps its `string` type. A controller without it throws `InvalidControllerException` when `Rest::resource()` registers its routes; read it with `UsersController::resourceClass()`.

Controllers expose **request-level hooks** (override as `protected` methods returning `void`):
- `beforeDetails(DetailsRequest)`, which runs once `viewAny` passed
- `beforeSearch(SearchRequest)`, `afterSearch(SearchRequest)`
- `beforeMutate(MutateRequest)`, `afterMutate(MutateRequest)`
- `beforeOperate(OperateRequest)`, `afterOperate(OperateRequest)`
- `beforeDestroy(DestroyRequest)`, `afterDestroy(DestroyRequest)`
- `beforeForceDestroy(ForceDestroyRequest)`, `afterForceDestroy(ForceDestroyRequest)`
- `beforeRestore(RestoreRequest)`, `afterRestore(RestoreRequest)`

Use these for request-shaped concerns (preprocessing the request payload, logging the call). Use **resource hooks** (above) for model-lifecycle concerns — they fire regardless of which endpoint hit them.

### 3. Routing

In `routes/api.php`:

```php
use Lomkit\Rest\Facades\Rest;

Rest::resource('users', \App\Rest\Controllers\UsersController::class);

// Opt into soft-delete routes (off by default):
Rest::resource('posts', \App\Rest\Controllers\PostsController::class)->withSoftDeletes(); // both restore + force
// or selectively:
Rest::resource('posts', \App\Rest\Controllers\PostsController::class)->withSoftDeletes('restore');
```

The name must be one URI segment (letters, digits, `-`, `_`). Dotted names (`users.posts`) and slashed names (`admin/users`) throw `InvalidResourceRouteException`: wrap the call in `Route::prefix('admin')` instead. `Rest::resource()` takes two arguments.

`Rest::resource()` returns a builder with these options. Endpoint keys are `details`, `search`, `mutate`, `operate`, `destroy`, `restore` and `forceDestroy` (or the `Lomkit\Rest\Http\Routing\Endpoint` cases); an unknown key throws. The result does not depend on the call order.

```php
Rest::resource('posts', PostsController::class)
    ->withSoftDeletes('restore')                 // restore and/or forceDestroy; none = both
    ->except('destroy')                          // or ->only('details', 'search')
    ->names('blog.posts')                        // name prefix; ->names([...]) or ->name('search', 'posts.find') for exact names
    ->middleware('verified')                     // added after the package middleware
    ->middlewareFor('operate', 'throttle:5,1')   // added after the resource middleware
    ->withoutMiddleware('verified')              // or ->withoutMiddlewareFor('details', 'verified')
    ->register();                                // optional: routes register when the builder is released
```

`Rest::resource()` registers these routes. `Route::resourceVerbs()` changes the verbs through the keys `search`, `mutate`, `actions`, `restore` and `forceDestroy` (each must be one URI segment):

| Method | URI | Endpoint | Purpose |
| --- | --- | --- | --- |
| `GET` | `/users` | `details` | Schema for the resource (fields, relations, scopes, limits, actions, instructions, rules) |
| `POST` | `/users/search` | `search` | Filterable/sortable/paginated read |
| `POST` | `/users/mutate` | `mutate` | Batch create/update + relation operations |
| `POST` | `/users/actions/{action}` | `operate` | Run a custom action (`{action}` is its `uriKey`) |
| `DELETE` | `/users` | `destroy` | Batch (soft-)delete |
| `POST` | `/users/restore` | `restore` | Opt-in via `withSoftDeletes()` |
| `DELETE` | `/users/force` | `forceDestroy` | Opt-in via `withSoftDeletes()` |

Every route carries the `EnforceExpectsJson` middleware, which adds `application/json` to the `Accept` header, so errors come back as JSON. Group middleware runs before it. Route names only have to be unique within one resource; two resources with the same name or URI still overwrite each other, as with Laravel routes. Laravel Precognition is supported when `rest.precognition.enabled = true` — clients send `Precognition: true` and the request returns `204` after validation without touching the controller body, or `422` with errors. The checks made before validation still run, so a denied check answers `403` first.

## `details` endpoint

`GET /api/users` returns the resource schema, filtered by the current user's permissions:

```json
{
  "data": {
    "actions":      [{"uriKey": "publish-posts", "name": "...", "fields": [...], "meta": {...}, "standalone": false, "targeted": false}],
    "instructions": [{"uriKey": "odd-even-id",   "name": "...", "fields": [...], "meta": {...}}],
    "fields":       ["id", "name", ...],
    "scout_fields": [...],
    "limits":       [10, 25, 50],
    "scopes":       [{"name": "publishedSince", "fields": [...], "meta": {...}}],
    "relations":    [{"resource": "PostResource", "relation": "posts", "constraints": {...}}, ...],
    "rules":        {"all": {...}, "create": {...}, "update": {...}}
  }
}
```

The `fields` of actions, instructions and scopes take `rules()` only: declaring `creationRules()` or `updateRules()` on them throws `InvalidFieldException` when the fields are built. They are described as input entries, which a frontend can render as a form:

```json
{"name": "since", "type": "date", "nullable": false, "default": null, "rules": ["date"], "meta": []}
```

`Enum` entries add `values`. Rules are strings: pipe strings are split, `Stringable` rules are cast to string, other rule objects are named by their snake class name (`enum`, `password`), and closures are left out.

Frontends typically call `details` once to drive form rendering, then `search`/`mutate` based on it.

## `search` payload

`POST /api/users/search` (every key is optional):

```json
{
  "search": {
    "text":   {"value": "needle"},
    "scopes": [{"name": "publishedSince", "parameters": ["2026-01-01"]}],
    "filters": [
      {"field": "id", "operator": ">", "value": 1, "type": "or"},
      {"nested": [
        {"field": "user.posts.id", "operator": "<", "value": 2},
        {"field": "user.id", "operator": ">", "value": 3, "type": "or"}
      ]}
    ],
    "sorts":   [{"field": "user_id", "direction": "desc"}],
    "selects": [{"field": "id"}],
    "includes": [
      {"relation": "posts",
       "filters":  [{"field": "id", "operator": "in", "value": [1, 3]}],
       "sorts":    [{"field": "created_at", "direction": "desc"}],
       "limit":    2},
      {"relation": "posts", "alias": "draftPosts",
       "filters":  [{"field": "published", "value": false}]}
    ],
    "aggregates": [
      {"relation": "stars", "type": "max", "field": "rate",
       "filters": [{"field": "approved", "value": true}]}
    ],
    "instructions": [
      {"name": "odd-even-id", "fields": [{"name": "type", "value": "odd"}]}
    ],
    "gates": ["create", "view", "update", "delete", "restore", "forceDelete"],
    "page":  2,
    "limit": 10
  }
}
```

**Filter operators**: `=`, `!=`, `>`, `>=`, `<`, `<=`, `like`, `not like`, `in`, `not in`.
**Filter `type`**: `and` (default) or `or`. Use `nested` to group filters with their own logical operator. `field` may traverse declared relations and pivot data (`user.posts.id`, `languages.pivot.boolean`).
**Scope `parameters`** is a positional list: each value binds to the scope field at the same position, is validated by its rules, cast to its type and filled from its `default()`. A position is required unless its field has a default; a missing, extra or wrongly typed parameter is a `422` under `search.scopes.{index}.parameters`. A rule that names another field of the same scope, such as `gte:min`, compares with the parameter at that field's position. Scopes are prohibited with `text` (Scout).
**`selects`** lists the selectable fields to return (`writeOnly()`, `selectable(false)` and `virtual()` fields are a `422` under `search.selects.{index}.field`); without it a row holds every selectable field of its resource. Include `selects` use the related resource's fields.
**`sorts`** apply in order and accept the sortable fields only (`Json`, `sortable(false)`, `writeOnly()` and `virtual()` fields without `sortUsing()` are a `422` under `search.sorts.{index}.field`). `direction` is `asc` or `desc` (`Lomkit\Rest\Fields\SortDirection`), `asc` when omitted. Without `sorts`, `defaultOrderBy()` applies. With `text`, sorts accept the `scoutFields()`.
**Aggregate `type`** is required, one of `min`, `max`, `avg`, `sum`, `count`, `exists` (`Lomkit\Rest\Fields\AggregateFunction`). `field` is required for `min/max/avg/sum`, omit for `count/exists`. `min`/`max` take a field of the related resource that is sortable by its own column (`isSortableByColumn()`: sortable by default or `sortable()`, not `virtual()`, not `writeOnly()`; `sortUsing()` does not count, so a `Json` field with `sortUsing()` is refused) and is not a `Boolean` (an app type extending `Boolean` included), on every database; an `ID` with UUID keys (`HasUuids` or `keyType('uuid')`) works for `min`/`max` on every database and returns the same string (PostgreSQL reads it as text); `avg`/`sum` also need a `Number` field (`Number`, `Integer` or an app type extending `Number`). Another field is a `422` under `search.aggregates.{index}.field`. An optional `alias` (a string) renames the response key, which is `{relation}_{type}_{field}` otherwise (`stars_max_rate`, `stars_count`).
**`page`** is an integer of at least `1`; `limit` must be one of the resource's `limits()`.
**`includes`** can recursively re-use `filters`, `sorts`, `scopes`, `limit`, `selects` — but **not nested `includes`** (load chained relations as separate include entries). Includes and aggregates always run in SQL, so they are validated against the related resource's fields and operators, also next to `text`; a missing or undeclared `relation` is a `422`.
**Include `alias`** renames the response key for that include, which lets the same relation appear several times under different constraints. It must look like an identifier (`^[A-Za-z_][A-Za-z0-9_]*$`), be unique across the includes, and not collide with a field, a relation, or the gates key of the resource. It is used verbatim — no snake_casing — and is rejected on a dotted relation path (use a nested include there).
**`gates`** asks the server to evaluate the listed policy abilities per row and embed the result under the configured key (default `gates`); with `rest.gates.message.enabled = true` the value becomes `{allowed: bool, message: string}`.
**`text`** triggers the Scout path when `text.value` is filled (at most 255 characters); only models using `Laravel\Scout\Searchable` accept it, and some standard search features become Scout-driver-dependent. A blank, `null` or missing `text.value` runs the normal search and returns the normal list. `text.trashed` (`with`/`only`) needs a filled `text.value`. `viewAny` is checked before any search runs, and with a text search `searchQuery()` also applies to the loaded page, while its order and `total` stay the index's.

**Response** is Laravel's paginator JSON:

```json
{
  "current_page": 1,
  "data":        [{"id": 1, "name": "...", "gates": {"authorized_to_update": true}}],
  "from": 1, "to": 50, "per_page": 50, "last_page": 4, "total": 187,
  "meta":        {"gates": {"authorized_to_create": true}}
}
```

## `mutate` payload

`POST /api/users/mutate` performs **batched** create/update with relation operations:

```json
{
  "mutate": [
    {"operation": "create",
     "attributes": {"name": "Jane"},
     "relations": {
       "posts": [
         {"operation": "create", "attributes": {"title": "Hello"}},
         {"operation": "attach", "key": 42, "pivot": {"role": "owner"}}
       ]
     }},
    {"operation": "update", "key": 7, "attributes": {"name": "John"},
     "relations": {
       "roles": [{"operation": "sync", "key": [1, 2, 3], "without_detaching": false}]
     }}
  ]
}
```

Per-item keys:

| Key | Type | Used by | Purpose |
| --- | --- | --- | --- |
| `operation` | string | all | `create`, `update`; in `relations` also `attach`, `detach`, and `sync`, `toggle` on many-to-many relations only |
| `attributes` | object | create / update / sync / toggle | Field values; only the fields the operation can write, validated by the field rules and the resource rules |
| `key` | int / string / array | update / attach / detach / sync / toggle | Target model id(s) |
| `relations` | object | all | Map of `relationName → [operation objects]` (recursive) |
| `pivot` | object | many-to-many `create`/`update`/`attach`/`sync`/`toggle` (refused on `detach`) | Pivot values; only the pivot fields the operation can write, validated by their rules |
| `without_detaching` | bool | `sync` | When `true`, behaves like `syncWithoutDetaching` |

Writes go through the fields:

- `create` accepts the creatable fields, and `update`, `sync` and `toggle` accept the updatable fields (`Lomkit\Rest\Fields\MutationOperation::writeOperation()` maps the operation). Fields are creatable and updatable by default; `ID` and `readonly()` fields are not, so declare `->creatable()` on a key the client sends (a UUID). `writeOnly()` fields are accepted; a `virtual()` field only with `fillUsing(Closure(Model, mixed): void)`, which runs after the other fields of the entry. Another key is a `422` under `{entry}.attributes`: `The created_at attribute is not writable on create.` (`on update.` for `sync` and `toggle`), with or without Precognition.
- Each attribute is validated by its field: the type's base rule (`null` refused unless `nullable()`, arrays refused except on `Json`), `rules()`, and `creationRules()` or `updateRules()`. A rule naming another declared field (`required_if:featured,true`, `after_or_equal:published_on`) compares with that attribute in the same entry.
- In a nested write, the keys the relation sets win over the entry's attributes: the parent key and morph type (`getMorphClass()`, so morph map aliases) of `HasOne`, `HasOneOfMany`, `HasMany`, `MorphOne`, `MorphOneOfMany`, `MorphMany`, and the foreign key and morph type that `BelongsTo` and `MorphTo` associate. The parent relation's keys win over the entry's own `BelongsTo` or `MorphTo`, and the `BelongsTo` and `MorphTo` keys are read after the `mutating()` hook, so a key the hook associates is kept.
- `pivot` goes through the pivot fields of the relation (see Relationships): `create` and `attach` add a pivot row and accept the creatable pivot fields with `creationRules()`; `update`, `sync` and `toggle` accept the updatable pivot fields with `updateRules()` (`MutationOperation::pivotWriteOperation()` maps the operation); `detach` refuses `pivot`. `readonly()` pivot fields are refused, `writeOnly()` ones accepted. Each value is validated by its field (type base rule, `nullable`, `rules()`), with errors under `{entry}.pivot.{key}`, also when `pivot` is missing; another key is a `422` under `{entry}.pivot` (`The created_at attribute is not writable on create.`). A rule naming another pivot field compares with the pivot of the same entry. Only the writable pivot keys reach the database. Limit: a row that `update`, `sync` or `toggle` adds gets the update rules, since validation runs before the database is read; use `attach` to add a row with the creation rules.
- A custom relation lists its operations as `MutationOperation` cases in `mutationOperations()`, and returns the keys it sets in `beforeMutating()` from `ownedAttributes(Model $model): array`.

**Response**: grouped affected ids — `{"created": [72979], "updated": [7, 12], ...}`. Do **not** expect the full models back; re-query via `search` if you need them.

`HasOneThrough` / `HasManyThrough` cannot be mutated — chain through the intermediate relation instead.

## Custom actions (`operate`)

Generate:

```bash
php artisan rest:action SendWelcomeNotificationAction   # → app/Rest/Actions/
```

```php
class SendWelcomeNotificationAction extends \Lomkit\Rest\Actions\Action
{
    // Optional. Set to true if the action operates without targeting models.
    // public bool $standalone = true;

    // Optional. Set to true to require the caller to name the models by id.
    // Mutually exclusive with $standalone.
    // public bool $targeted = true;

    // Optional. Cap on how many ids a targeted action accepts (default 1000).
    // public int $maxResources = 1000;

    // Optional. Batch size when processing models (default 100).
    // public int $chunkCount = 100;

    public function handle(array $fields, \Illuminate\Support\Collection $models): void
    {
        // $fields['expires_at'] is a CarbonImmutable; $fields['notify'] is a bool, true when not sent
        foreach ($models as $model) { /* ... */ }
    }

    public function fields(\Lomkit\Rest\Http\Requests\RestRequest $request): array
    {
        return [
            \Lomkit\Rest\Fields\Date::make('expires_at')->rules('required'),
            \Lomkit\Rest\Fields\Boolean::make('notify')->default(true),
        ];
    }
}
```

Register on the Resource:

```php
public function actions(RestRequest $request): array
{
    return [
        SendWelcomeNotificationAction::make()->meta(['color' => '#FFFFFF']), // merges; getMeta() reads
    ];
}
```

Call:

```
POST /api/users/actions/send-welcome-notification-action
{
  "fields": [{"name": "expires_at", "value": "2026-04-29"}],
  "search": {"filters": [{"field": "has_received_welcome", "value": false}]}
}
```

Note the **`fields` payload is an array of `{name, value}` pairs**, not an object, and each name may appear only once (a repeated name is a `422` under `fields.{index}.name`). The `search` block resolves the target models (whole `search` schema is supported, except `text`: a text search on an action is a `422`); when sent, it must be an object. Standalone actions (`->standalone()` or `public bool $standalone = true`) omit `search` and receive an empty collection.

An action has exactly one of three targeting states, which decides what the request body may carry:

| State | `search` | `resources` | Models impacted |
|---|---|---|---|
| `standalone()` | prohibited | prohibited | none, `handle()` gets an empty collection |
| classic (default) | optional | prohibited | whatever `search` resolves — **every model when `search` is omitted** |
| `targeted()` | prohibited | required, array of ids | exactly the ids named, minus any the resource's perimeter hides |

Reach for `targeted()` when an action must never be able to hit every model by accident: the caller has
to name its targets, and omitting `resources` is a `422` rather than a silent mass update. Unknown ids
are also a `422`; ids the caller is not allowed to see are skipped. `standalone()` and `targeted()` are
mutually exclusive and throw `InvalidActionStateException` if combined. `resources` is capped at
`$maxResources` ids per request (default 1000) — lower it per action when the blast radius should be
smaller.

```jsonc
// POST /api/users/actions/deactivate-users
{ "resources": [1, 5, 9], "fields": [{ "name": "reason", "value": "spam" }] }
```

**Fields are typed `Lomkit\Rest\Fields\*` objects.** Each field is validated with its type rules, `nullable()` and `rules()` as a normal Laravel validation: presence and cross-field rules (`required`, `required_if:status,published`, `present`, …) fire even when a field is absent from the `fields` array. Validation errors are keyed by field name — `fields.{name}` (e.g. `fields.expires_at`) — while an unknown or missing field name is reported positionally as `fields.{index}.name`. An `Enum` field must name its enum class (`Enum::make('status', PostStatus::class)`), because action fields are not bound to a model. Field names must not be numeric: `Integer::make('2')` throws `InvalidFieldException`. The request's `messages()` and `attributes()` apply to the `fields.{name}` keys, and so do translation lines keyed by the full key (`validation.custom.fields.{name}.{rule}`); `validation.attributes.{name}` still names the field.

**Field rules run in the request's validator.** A rule that names another input compares with that input: the field parameters of Laravel's dependent rules (`required_if`, `gte`, `after`, `same`, …), `date_equals` and `in_array`, as strings or arrays, in `Rule::date()` / `Rule::numeric()` / `Rule::string()` / `Rule::array()` objects and inside `Rule::when()`. Other rule objects see the whole request: a `DataAwareRule` receives the whole payload, with each input value under `fields.{name}`, and rule objects and closures receive the full key (`fields.{name}`, `search.instructions.{index}.fields.{name}`) as their attribute.

**`handle()` receives a name => value map**: each value cast to its field type (`Date`/`DateTime` → `CarbonImmutable`, `Integer` → `int`, `Boolean` → `bool`, `Enum` → the enum case), with `default()` values filled in. A field that is absent and has no default is missing from the array, so read optional fields with `$fields['name'] ?? null`; an entry sent without `value` counts as absent.

**Response**: `{"data": {"impacted": 150}}`.

**Abilities.** Before validation, `operate` resolves the action: an unknown action is a `404` for a user allowed `viewAny` on the resource and a `403` for the others. A known action checks, in this order, `viewAny` on the resource, the abilities the resource's `abilities()` returns for `Endpoint::Operate` (none by default), then those `Action::abilities(): array` returns (`['viewAny']` by default, for every action, standalone and targeted ones included). Both lists add to `viewAny`: an action returning `['export']` checks `viewAny`, then `export`. An action returning `[]` runs with none of these checks (no `viewAny`, no `Endpoint::Operate` abilities, and its search does not check `viewAny` on the resource): use it for an action any user may run, guests included. Includes, aggregates and filters on relations in the action's `search` still need `viewAny` on the related resource, as in a search.

**Queuing**: implement `Illuminate\Contracts\Queue\ShouldQueue` to dispatch one job per chunk of `$chunkCount` models. Combine with `Lomkit\Rest\Contracts\BatchableAction` and use `withBatch(array $fields, PendingBatch $batch): void` to register `then`/`catch`/`finally` callbacks. The synchronous `handle()`, `withBatch()` and the queued jobs all receive the same cast map. Customise `$connection` and `$queue` properties to route jobs. A job carries the action, the cast map and the models, not the resource's declarations: the worker evaluates them again, with no user on the request, and the action and its field values cannot hold closures.

## Instructions (custom search refinements)

Generate:

```bash
php artisan rest:instruction OddEvenIdInstruction   # → app/Rest/Instructions/
```

```php
class OddEvenIdInstruction extends \Lomkit\Rest\Instructions\Instruction
{
    public function handle(array $fields, \Illuminate\Database\Eloquent\Builder $query): void
    {
        $query->whereRaw('MOD(id, 2) = ?', [$fields['type'] === 'odd' ? 1 : 0]);
    }

    public function fields(RestRequest $request): array
    {
        return [\Lomkit\Rest\Fields\Text::make('type')->rules('required', 'in:odd,even')];
    }

    // Scout variant — implement when this Instruction must also work under full-text search.
    // The base handleScout() throws InvalidInstructionException (a LogicException).
    // public function handleScout(array $fields, ScoutBuilder $builder): void { ... }
}
```

Register in `Resource::instructions()`. Clients invoke them via the `instructions` key inside `search`:

```json
"instructions": [{"name": "odd-even-id-instruction", "fields": [{"name": "type", "value": "odd"}]}]
```

`->meta([...])` (which merges) and `getMeta()` are also available on Instructions.

Instruction `fields()` are typed and validated exactly like Action fields: full Laravel validation (including `required` on absent fields), with errors keyed by name under `search.instructions.{index}.fields.{name}` (custom translation lines go under `validation.custom.search.instructions.*.fields.{name}.{rule}`); a name repeated in `fields` is a `422` under `search.instructions.{index}.fields.{position}.name`. The fields of Scout instructions (`scoutInstructions()`) are validated the same way. `handle()` and `handleScout()` receive the same cast name => value map as an action, with defaults filled in.

## Relationships

All relations must extend `Lomkit\Rest\Relations\Relation` (do **not** use `Illuminate\Database\Eloquent\Relations\*`) and target a **Resource class**, not a model. Supported types: `BelongsTo`, `HasOne`, `HasOneOfMany`, `HasOneThrough`, `MorphOne`, `MorphOneOfMany`, `HasMany`, `HasManyThrough`, `MorphMany`, `BelongsToMany`, `MorphToMany`, `MorphedByMany`, `MorphTo`.

```php
public function relations(RestRequest $request): array
{
    return [
        \Lomkit\Rest\Relations\BelongsTo::make('company', CompanyResource::class)
            ->requiredOnCreation()
            ->prohibitedOnUpdate(),

        \Lomkit\Rest\Relations\BelongsToMany::make('roles', RoleResource::class)->pivotFields([
            Integer::make('level')
                ->rules('min:1')
                ->creationRules('required'),
            Text::make('granted_by')->updatable(false),   // written on create and attach only
            Text::make('note')->writeOnly(),              // written, never returned
            DateTime::make('created_at')->readonly(),     // returned, never written
        ]),
    ];
}
```

Mutation constraints (chainable, may take a closure for conditional logic): `requiredOnCreation()`, `prohibitedOnCreation()`, `requiredOnUpdate()`, `prohibitedOnUpdate()`.

Many-to-many relations (`BelongsToMany`, `MorphToMany`, `MorphedByMany`) declare their pivot columns with typed `Field` objects in `pivotFields([...])` (`withPivotFields()` and `withPivotRules()` are gone; strings and duplicate names throw `InvalidFieldException`). Each pivot field is a column of the pivot table, written as sent: a `virtual()` pivot field or one with `fillUsing()` throws `InvalidFieldException`. `getPivotFields()` returns a `FieldSet` bound to the pivot class of the Eloquent relation (the `using()` class or `Pivot`), so an `Enum` pivot field reads the pivot casts. The pivot fields decide what `pivot` accepts on each operation (see the `mutate` payload) and what the `pivot` of an included row returns: the selectable pivot fields only, never `writeOnly()` or `selectable(false)` ones. Filters on `{relation}.pivot.{key}` accept any declared pivot field. A custom relation with pivot fields implements `Lomkit\Rest\Contracts\PivotRelation` (the `HasPivotFields` trait does).

## Delete / restore / force

```
DELETE /api/users          body: {"resources": [5, 6]}      // destroy (soft if SoftDeletes is used)
POST   /api/users/restore  body: {"resources": [5, 6]}      // requires withSoftDeletes()
DELETE /api/users/force    body: {"resources": [5, 6]}      // requires withSoftDeletes()
```

All three return the affected records with the selectable fields of their resource: `{"data": [{...}], "meta": []}`. They take no `search`: an empty array is accepted, and any other `search` (`null` and blank strings included) is a `422` under `search`, so their rows carry no `selects`, gates or aggregates. Each entry of `resources` is a key typed like the resource's `ID` field (an integer for integer keys; a string, UUID or ULID otherwise), so a list or an object in `resources` is a `422` under `resources.{index}`.

## Authorization model

Two layers, both controlled by `config/rest.php`:

### 1. Policy authorization (`authorizations.enabled`, default `true`)

Every model touched by a search/mutate/delete is checked against its Policy. Required policy methods:

| Policy method | Triggered by |
| --- | --- |
| `viewAny` | `search`, `details`, `operate` (unless the action's `abilities()` returns `[]`), and every resource reached by an include, an aggregate or a relation filter (each level, pivot columns included) |
| `view` | per-row visibility, includes |
| `create` | `mutate` create, once per created resource class, with the model class |
| `update` | `mutate` update |
| `replicate` | `mutate` create when duplicating from existing data |
| `delete` | `destroy` |
| `restore` | `restore` |
| `forceDelete` | `forceDestroy` |
| `attach{Model}($user, $parent, $related)` | attach/sync on a relation (e.g. `attachRole`) |
| `detach{Model}($user, $parent, $related)` | detach/sync on a relation (e.g. `detachRole`) |

**Define a policy for every model you expose**, including the attach/detach methods for many-to-many relations you allow mutating. Missing methods will block the operation. `php artisan rest:policy PostPolicy --model=Post` writes a read-only starting point.

**Class-level checks run before validation and before any query.** The request checks its endpoint abilities (`viewAny` for `search` and `details`, none for `mutate`, `destroy`, `restore` and `forceDestroy`, and for `operate` `viewAny` followed by the resource's `Endpoint::Operate` abilities and the action's `abilities()`), then `viewAny` on every resource the payload reaches and `create` for every created resource. A denied check answers `403` (or the status of the policy's response) even for an invalid payload or a Precognition request. The request's own `authorize()` runs first, and both must pass. Row checks (`view`, `update`, `delete`, attach/detach, ...) still run when the rows are loaded.

To change the endpoint abilities of one resource, override `abilities()` on it:

```php
public function abilities(\Lomkit\Rest\Http\Routing\Endpoint $endpoint, RestRequest $request): array
{
    return $endpoint === \Lomkit\Rest\Http\Routing\Endpoint::Destroy ? ['deleteAny'] : parent::abilities($endpoint, $request);
}
```

The list replaces the endpoint's default: returning `['export']` for `Endpoint::Search` drops `viewAny` there. Keep `viewAny` in the list to add an ability (`['viewAny', 'export']`). `Endpoint::Operate` is the exception: its list adds to `viewAny` (see Custom actions).

These checks go through the resource's `authorizedTo()`, so an override of it applies to them. A denied check made before validation throws `\Lomkit\Rest\Exceptions\AuthorizationDenied` (an `AuthorizationException`) holding every denial of these checks (path such as `search.includes.0`, ability, resource class, key, message; at most 50). The body stays `{"message": ...}` with the message and status of the first denial, so `Response::denyAsNotFound()` still gives `404`. To add the denials to the JSON, register `$exceptions->render(new \Lomkit\Rest\Exceptions\DenialsRenderer);` in `bootstrap/app.php`'s `withExceptions()`; it leaves `404` responses unchanged. Row checks (`view`, `update`, `delete`, `restore`, `forceDelete`, attach/detach) still throw Laravel's `AuthorizationException`, without denials.

The queries check `viewAny` again on each resource they search (and `create` on each resource they create), reusing the decisions made before validation. The endpoint's own resource is the exception: its endpoint abilities stand in for `viewAny`, so when they leave `viewAny` out (a resource override of `abilities()`, or an action whose `abilities()` returns `[]`) the queries do not check it there. With `authorizations.strict = true` (default `false`, meant for test suites), a check on another resource that was not made before validation throws `UnplannedCheckException`: a custom rule node that reaches a resource records it in `compile()` with `$into->reach(Check::onClass($resource, 'viewAny', $attribute))`.

Within a request, each distinct check (ability, resource, model or model class) runs the policy once and the later checks reuse its decision, so `GateEvaluated` listeners and `Gate::after()` see one evaluation per distinct check. Policies run with `Gate::forUser($request->user())`, and a change of user starts a new set of decisions. The package drops the decisions on a model after writing it; a hook that writes a model itself calls `$request->context()->authorizer()->forget($model)`.

The cross-request cache is off by default (`authorizations.cache.enabled = false`). When on, it stores each decision as scalars for `authorizations.cache.default` minutes (default `5`), keyed by guard, user class and key, resource, ability and model class and key; `create` checks and checks on unsaved models are not cached. Override `cacheAuthorizationFor()` on the resource, or use the `\Lomkit\Rest\Concerns\Resource\DisableAuthorizationsCache` trait, to tweak behaviour.

### 2. Gates (`gates.enabled`, default `true`)

When the client passes `"gates": [...]` in `search`, the listed abilities are evaluated per row and attached under the configured `gates` key. The keys returned (`authorized_to_view`, `authorized_to_create`, etc.) are configurable under `rest.gates.names`.

`rest.gates.message.enabled = true` switches the response to `{allowed, message}`, where `message` comes from a `Response::deny('...')` returned from the policy method. Use the `\Lomkit\Rest\Concerns\Resource\DisableGates` trait on a specific resource to opt out without flipping the global config.

## Custom Response

`php artisan rest:response UserResponse` scaffolds a class with a `map($model, $responseModel)` method that returns the array shape sent to the client. Assign on the Resource:

```php
public static string $response = \App\Rest\Responses\UserResponse::class;
```

Use sparingly — overriding the response can bypass authorization shaping and field whitelisting if you reference the model directly.

## OpenAPI documentation

The package documents its endpoints through a Scramble extension.

```bash
composer require dedoc/scramble:^0.13
```

The package registers the extension itself: `config/scramble.php` needs no change.

Docs are served at `/docs/api`. Fields, validation rules, relations, search filters, and mutate payloads are inferred automatically from the resources. The mutate `attributes` list the writable fields only, with their field and resource rules for `create` and `update`. Relations come from the relation objects of `relations()` (their class name, `mutationOperations()` and number of entries), and the `pivot` of a many-to-many entry lists the writable pivot fields with their rules for `create` and `attach`, then `update`, `sync` and `toggle`.

## Precognition

Set `rest.precognition.enabled = true` (default `false`). Clients send `Precognition: true` (and optionally `Precognition-Validate-Only`) on any endpoint, and the request is authorized (the class-level checks above, a denied one answers `403`), validated and short-circuited before the controller body runs — `204 No Content` on success, `422` on validation failure. Useful for live frontend form validation. A name in `Precognition-Validate-Only` also covers the keys under it: `search` validates `search.limit`, `search.filters.0.field` and every other key of the search, while `search.filters` leaves `search.limit` out.

## Artisan commands shipped by the package

- `rest:resource {Name} --model=Model` — generate a Resource. Without `--model`, the model is guessed from the name (`PostResource` → `App\Models\Post`) when that class exists; otherwise the `$model` line is left out and the command warns.
- `rest:resource {Name} --model=Model --generate` (`-G`) — read the model's table and declare one field per column: the key as `ID::make(<key name>)`, the type from the cast or the column type (`Enum` for a backed enum cast, `Json` for JSON casts, `Text` with `rules('json')` and a note for a `json` or `jsonb` column without one, unknown types as `Text` with a note), `nullable()` for nullable columns, `readonly()` for timestamps and auto-increment or generated columns, `writeOnly()` for `$hidden` columns (a hidden column the model does not let fill is left out with a note), `rules('max:N')` when MySQL or PostgreSQL report a string length, and `creationRules('required')` plus `Rule::unique(Model::class, 'column')` for required and uniquely indexed columns. It also declares the relations: each public model method with a declared Eloquent relation return type (`BelongsTo`, `HasOne`, `HasMany`, `BelongsToMany`, `MorphOne`, `MorphMany`, `MorphToMany`, `morphedByMany()` as `MorphedByMany`, `HasOneThrough`, `HasManyThrough`) whose related model has a resource under `app/Rest/Resources` (or is the model itself), e.g. `BelongsTo::make('author', UserResource::class)->requiredOnCreation()` with `author_id` made `readonly()`, and `pivotFields([...])` typed from the pivot table for `withPivot()` columns and `withTimestamps()` (pivot timestamps `readonly()`). Relations to a model without a resource, and `MorphTo`, are skipped. It prints `PostResource ... 8 fields, 1 relation`, one `Skipped:` line per relation not generated (with the `rest:resource` command to run first for a missing resource) and one `Note:` line per column to review. It exits 1 before writing anything when the model is missing, is not a model or cannot be instantiated (an abstract model), or its table is missing; rerun with `--generate --force` to regenerate an existing resource. Interactively, without the flag, it asks `Generate the fields and relations from the <table> table?` (default yes) when the table exists; with `-n` and no flag the resource declares only the key. Review the generated fields before exposing the resource.
- `rest:controller {Name} --resource=ResourceClass` — generate a Controller. Without `--resource`, the resource is guessed the same way (`PostsController` → `PostResource`); otherwise the `$resource` line is left out and the command warns. Set `$resource` before you register the controller with `Rest::resource()`: a controller without it stops the route files and every Artisan command, the generators included.
- `rest:base-resource` / `rest:base-controller` — generate the empty abstract base classes `app/Rest/Resources/Resource.php` and `app/Rest/Controllers/Controller.php` that all your resources/controllers should extend. `rest:resource` and `rest:controller` create them when they are missing. Override the `*Query` hooks in the base resource to constrain every resource.
- `rest:action {Name}` — scaffold an Action.
- `rest:instruction {Name}` — scaffold an Instruction.
- `rest:response {Name}` — scaffold a custom Response class.
- `rest:policy {Name} --model=Model` — generate a read-only policy in `app/Policies`: `viewAny` and `view` return `true`, `create`, `update` and `delete` (plus `restore` and `forceDelete` with `SoftDeletes`) return `false`. The user parameter is typed with the model of the default guard's user provider. Without `--model`, the model is guessed from the name (`PostPolicy` → `App\Models\Post`). A model that Gate already maps to another policy keeps it: nothing is written, and with `--force` the file is written but Gate still uses the other policy until you register the new one with `Gate::policy()`.
- `rest:quick-start --model=Post --model=Tag` — for each model, write the resource from its table (as `--generate`), the controller and, when Gate finds no policy, a read-only policy, then append `Rest::resource('posts', PostsController::class)->middleware('auth:sanctum')` to `routes/api.php` once per controller. Related models that are not chosen are skipped. Interactively it asks for the models; with `-n`, `--model` is required, and `--install-api` is required when `routes/api.php` is missing. It ends with a check in a fresh process (routes authenticated, a policy found per model) and exits 1 when blocked. Existing files are reported as `kept`, so a rerun changes nothing.
- The generators keep an existing class unless `--force` (`-f`) is passed, and read the published `stubs/rest.<name>.stub` before the package stubs. Stubs published under 2.x generate untyped properties, which are a fatal error in 3.x: delete them, or publish them again with `php artisan stub:publish --force`.

## Working conventions and gotchas

- **Don't write custom controller methods.** If you find yourself wanting to, the right tool is almost always an `Action` (mutation) or an `Instruction` (query). Falling back to a plain Laravel controller bypasses the package's authorization layer.
- **Whitelist by declaration.** Adding a column to the table doesn't expose it — it must be in `fields()`. Same for scopes, relations, actions, instructions.
- **Relation classes are the package's, not Eloquent's.** Use `Lomkit\Rest\Relations\HasMany` (not `Illuminate\Database\Eloquent\Relations\HasMany`), pass `::make('name', TargetResource::class)`, and target the **Resource**, not the model.
- **Soft deletes are opt-in at the route.** Even if the model uses `SoftDeletes`, `restore` / `force` routes only register when `->withSoftDeletes()` is chained onto `Rest::resource(...)`.
- **Don't bypass policies by toggling `authorizations.enabled = false`** — write the policy. The package's threat model assumes it's on, including `viewAny`, `replicate`, and `attach{Model}`/`detach{Model}`.
- **`mutate` is one batch but per-item-authorized.** A single denied item fails the whole batch.
- **`includes` are not recursive** — they accept `filters`/`sorts`/`scopes`/`limit`/`selects` but not nested `includes`. To go deeper, register chained relations on the intermediate resource and add an entry per level.
- **Action `fields` payload is an array of `{name, value}` pairs**, not an object — easy mistake: an object keyed by name is a `422`, `The fields field must be a list.` The same goes for instruction `fields`.
- **One validator per request.** A package request compiles its rule tree (`Lomkit\Rest\Rules\RestRule` nodes) against the payload into rules on explicit keys, then validates them in one pass, so `withValidator()` and `after()` on a request subclass run once and see every error. A `RestRule` is not a Laravel rule: a custom node implements `buildValidationRules(string $attribute, mixed $value): array` and only works in the `rules()` of a package request. To adjust the validator, use `withValidator()` or override `createDefaultValidator()` and call the parent: a request that defines `validator()` must build it from `validationData()` and `validationRules()`, and loses the names the package gives to input fields. Overrides of `validationData()`, `validationRules()`, `createDefaultValidator()` and `shouldValidatePrecognitiveAttribute()` must keep the parent's return types.
- **Scope `parameters` are positional.** They bind to the scope's fields in declared order, so reordering a scope's fields changes what clients must send. Only model local scopes run: `withTrashed`, `latest` and other Builder methods or macros cannot be called as scopes.
- **`HasOneThrough` / `HasManyThrough` can be searched/included but not mutated.**
- **Cache invalidation.** The authorization cache is off by default. Once turned on, a decision stays cached for `authorizations.cache.default` minutes, so policy changes show after that delay; keep it off while debugging policies, or use the `DisableAuthorizationsCache` trait on a resource.

## Pointers into the codebase

- Service provider & routing — `src/RestServiceProvider.php`, `src/Rest.php`, `src/Http/Routing/` (`Endpoint`, `PendingResourceRegistration`, `ResourceRegistrar` and the route value objects)
- Request classes per verb — `src/Http/Requests/{Search,Mutate,Operate,Destroy,Restore,ForceDestroy,Details}Request.php`
- Resource base class & traits — `src/Http/Resource.php`, `src/Http/ResourceDefinition.php` (the declarations of one resource for one request, kept by `src/Http/RestContext.php`), `src/Concerns/Resource/*` (`DisableGates`, `DisableAuthorizationsCache`, hooks, ...)
- Authorization — `src/Authorization/*` (`Authorizer`, `Check`, `Decision`, `Denial`, `DecisionMemo` and the decision stores), `src/Concerns/Authorizable.php`, `src/Exceptions/{AuthorizationDenied,DenialsRenderer,UnplannedCheckException}.php`; the checks made before validation are collected by the rule nodes (`RuleCompiler::reach()`) and run by `RestRequest::passesAuthorization()`
- Query layer — `src/Query/Builder.php`, `src/Query/ScoutBuilder.php`, and `src/Query/AttributeWriter.php`, which fills a model from the writable fields of a mutation
- Actions / Instructions / Scopes — `src/Actions/{Action,Actionable}.php`, `src/Instructions/{Instruction,Instructionable}.php`, `src/Scopes/Scope.php`
- Typed fields and input values — `src/Fields/*` (`Field`, `FieldSet`, `InputValues`, `MutationOperation`, `WriteRules`, `Details/InputFieldDetails`), clauses in `src/Query/Clauses/`
- Relations — `src/Relations/*.php`; pivot fields in `src/Concerns/Relations/HasPivotFields.php` (`src/Contracts/PivotRelation.php`), validated by `src/Rules/Mutate/MutatePivot.php`
- Validation rules used to validate the request bodies — `src/Rules/{Search,Mutate,Input,Resource}/*`, compiled into one rule set per request by `src/Validation/RuleCompiler.php`
- Scramble extension — `src/Scramble/LomkitLaravelRestApiOperationExtension.php`, with `RelationMutationType` (a relation entry), `WritableFieldsType` (writable fields per operation) and `RuleType` (the type a rule list implies)
- Generator stubs — `src/Console/stubs/*.stub`
