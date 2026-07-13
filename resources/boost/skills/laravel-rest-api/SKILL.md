---
name: laravel-rest-api
description: Use this skill when working with the `lomkit/laravel-rest-api` package — building or modifying Resources, Controllers, Actions, Instructions, registering REST routes via `Rest::resource()`, or writing client payloads for the `search`, `mutate`, `operate`, `details`, `destroy`, `restore` and `force` endpoints. Triggers include the `Lomkit\Rest\` namespace, the `Rest` facade, the `rest:*` artisan commands, and `app/Rest/Resources` / `app/Rest/Controllers` directories.
---

# Laravel Rest API (lomkit/laravel-rest-api)

`lomkit/laravel-rest-api` exposes Eloquent models through a small, fixed set of POST-driven endpoints. Instead of writing one controller per use case, you declare a **Resource** (what is exposed and how it can be queried/mutated) and a thin **Controller** that binds the resource to a route prefix. The package wires CRUD, search, batch mutations, custom actions, soft-delete handling, policy-based authorization and OpenAPI documentation on top of that pair.

Requirements: PHP 8.2+, Laravel 11/12/13.

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
- **Actions**: custom mutators exposed under `/{resource}/actions/{uriKey}`, runnable against a `search`-resolved set of models or standalone. Generate with:

  ```bash
  php artisan rest:action SendWelcomeNotificationAction
  ```

- **Instructions**: custom query refinements invoked from the `search` payload's `instructions` key. Generate with:

  ```bash
  php artisan rest:instruction OddEvenIdInstruction
  ```

- **Soft-delete endpoints**: opt-in `restore` and `force` routes via `->withSoftDeletes()` chained on `Rest::resource(...)`.
- **Authorization**: automatic per-model Policy checks (`viewAny`, `view`, `create`, `update`, `delete`, `restore`, `forceDelete`, `attach{Model}`, `detach{Model}`) plus client-requested per-row `gates`.
- **Custom Response**: override the output shape per Resource via `public static $response = UserResponse::class;`. Generate with:

  ```bash
  php artisan rest:response UserResponse
  ```

- **OpenAPI documentation**: native Scramble extension (`LomkitLaravelRestApiOperationExtension`) infers docs from Resources, or use the built-in Swagger UI at `/api-documentation`.
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
php artisan rest:quick-start                     # optional: scaffolds UserResource + UsersController and wires routes/api.php
```

`rest:quick-start` will also ensure `api: __DIR__.'/../routes/api.php'` is registered in `bootstrap/app.php` (Laravel 11+).

The `RestServiceProvider` and `Rest` facade are auto-registered via composer's `extra.laravel`.

## Core concepts

### 1. Resource — `app/Rest/Resources/{Name}Resource.php`

A Resource declares the **shape of what the API exposes** for an Eloquent model. Generate one with:

```bash
php artisan rest:resource UserResource --model=User
```

Override these methods (all receive `Lomkit\Rest\Http\Requests\RestRequest $request`, so any list can be conditionally narrowed by user/permission):

- `fields()` — array of column names the client may select / filter / sort / mutate on.
- `relations()` — array of `Lomkit\Rest\Relations\*` instances declared with `::make('relationName', TargetResource::class)`. Only declared relations are includable/mutatable.
- `scopes()` — local Eloquent scopes the client is allowed to invoke by name.
- `limits()` — allowed pagination `limit` values (default `[10, 25, 50]`; `$defaultLimit` property is `50`).
- `actions()` — `Lomkit\Rest\Actions\Action` instances exposed under `/actions/{uriKey}`.
- `instructions()` — `Lomkit\Rest\Instructions\Instruction` instances exposed under the `instructions` search key.
- `rules()`, `createRules()`, `updateRules()` — validation rules applied during mutate (`createRules`/`updateRules` are merged with `rules`).
- `defaultOrderBy()` — default sort (defaults to `['id' => 'desc']`).
- Resource hooks (model-lifecycle): `mutating`, `mutated`, `destroying`, `destroyed`, `forceDestroying`, `forceDestroyed`, `restoring`, `restored`. Signatures: `mutating(MutateRequest $request, array $requestBody, Model $model): void`; lifecycle ones take just `Model $model`.

For Scout-backed models (`Laravel\Scout\Searchable`), additionally:
- `scoutFields()`, `scoutInstructions()`, `searchScoutQuery(RestRequest $request, ScoutBuilder $builder)`.

The Resource also has static properties:
- `public static $model` — the Eloquent class (required).
- `public static $response = \Lomkit\Rest\Http\Response::class` — override to customise output (see "Custom Response").

Resources extending `app/Rest/Resources/Resource.php` (generated via `rest:base-resource`) may override:
- `searchQuery`, `mutateQuery`, `destroyQuery`, `restoreQuery`, `forceDeleteQuery` — return a modified `Builder` to apply baseline constraints (tenant scoping, soft-visibility, etc.).
- `performDelete`, `performRestore`, `performForceDelete` — override the actual operation on a model.

**Anything not declared in `fields()` / `relations()` / `scopes()` / `actions()` / `instructions()` is rejected.** This is the security model — don't bypass it.

### 2. Controller — `app/Rest/Controllers/{Name}sController.php`

```bash
php artisan rest:controller UsersController --resource=UserResource
```

Generated controllers are intentionally tiny:

```php
class UsersController extends Controller
{
    public static $resource = \App\Rest\Resources\UserResource::class;
}
```

Controllers expose **request-level hooks** (override as `protected` methods):
- `beforeDetails(DetailsRequest)`
- `beforeSearch(SearchRequest)`, `afterSearch(SearchRequest)`
- `beforeMutate(MutateRequest)`, `afterMutate(MutateRequest)`
- `beforeOperate(ActionsRequest)`, `afterOperate(ActionsRequest)`
- `beforeDestroy(DestroyRequest)`, `afterDestroy(DestroyRequest)`
- `beforeForceDestroy(DestroyRequest)`, `afterForceDestroy(DestroyRequest)`
- `beforeRestore(RestoreRequest)`, `afterRestore(RestoreRequest)`

Use these for request-shaped concerns (preprocessing the request payload, logging the call). Use **resource hooks** (above) for model-lifecycle concerns — they fire regardless of which endpoint hit them.

### 3. Routing

In `routes/api.php`:

```php
use Lomkit\Rest\Facades\Rest;

Rest::resource('users', \App\Rest\Controllers\UsersController::class);

// Opt into soft-delete routes (off by default):
Rest::resource('posts', \App\Rest\Controllers\PostsController::class)
    ->withSoftDeletes();                       // both restore + force
// or selectively:
Rest::resource('posts', \App\Rest\Controllers\PostsController::class)
    ->withSoftDeletes(['restore']);
```

`Rest::resource()` registers these routes (verbs and paths are fixed):

| Method | URI | Endpoint | Purpose |
| --- | --- | --- | --- |
| `GET` | `/users` | `details` | Schema for the resource (fields, relations, scopes, limits, actions, instructions, rules) |
| `POST` | `/users/search` | `search` | Filterable/sortable/paginated read |
| `POST` | `/users/mutate` | `mutate` | Batch create/update + relation operations |
| `POST` | `/users/actions/{action}` | `operate` | Run a custom action (`{action}` is its `uriKey`) |
| `DELETE` | `/users` | `destroy` | Batch (soft-)delete |
| `POST` | `/users/restore` | `restore` | Opt-in via `withSoftDeletes()` |
| `DELETE` | `/users/force` | `forceDelete` | Opt-in via `withSoftDeletes()` |

All endpoints require `Accept: application/json` (enforced by `EnforceExpectsJson` middleware). Laravel Precognition is supported when `rest.precognition.enabled = true` — clients send `Precognition: true` and the request returns `204` after validation without touching the controller body, or `422` with errors.

## `details` endpoint

`GET /api/users` returns the resource schema, filtered by the current user's permissions:

```json
{
  "data": {
    "actions":      [{"uriKey": "publish-posts", "name": "...", "fields": {...}, "meta": {...}, "is_standalone": false}],
    "instructions": [{"uriKey": "odd-even-id",   "name": "...", "fields": {...}, "meta": {...}}],
    "fields":       ["id", "name", ...],
    "scout_fields": [...],
    "limits":       [10, 25, 50],
    "scopes":       ["withTrashed", ...],
    "relations":    [{"resource": "PostResource", "relation": "posts", "constraints": {...}}, ...],
    "rules":        {"all": {...}, "create": {...}, "update": {...}}
  }
}
```

Frontends typically call `details` once to drive form rendering, then `search`/`mutate` based on it.

## `search` payload

`POST /api/users/search` (every key is optional):

```json
{
  "search": {
    "text":   {"value": "needle"},
    "scopes": [{"name": "withTrashed", "parameters": [true]}],
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
       "limit":    2}
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
**Aggregate `type`** values: `min`, `max`, `avg`, `sum`, `count`, `exists`. `field` is required for `min/max/avg/sum`, omit for `count/exists`.
**`includes`** can recursively re-use `filters`, `sorts`, `scopes`, `limit`, `selects` — but **not nested `includes`** (load chained relations as separate include entries).
**`gates`** asks the server to evaluate the listed policy abilities per row and embed the result under the configured key (default `gates`); with `rest.gates.message.enabled = true` the value becomes `{allowed: bool, message: string}`.
**`text`** triggers the Scout path; only models using `Laravel\Scout\Searchable` accept it, and some standard search features become Scout-driver-dependent.

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
    {"operation": "update", "key": 7, "attributes": {"name": "John"}},
    {"operation": "sync",   "key": [1, 2, 3], "without_detaching": false}
  ]
}
```

Per-item keys:

| Key | Type | Used by | Purpose |
| --- | --- | --- | --- |
| `operation` | string | all | `create`, `update`, `attach`, `detach`, `sync`, `toggle` |
| `attributes` | object | create / update | Field values; whitelisted by `fields()` and validated by `rules()` |
| `key` | int / string / array | update / attach / detach / sync / toggle | Target model id(s) |
| `relations` | object | all | Map of `relationName → [operation objects]` (recursive) |
| `pivot` | object | many-to-many `attach`/`sync`/`toggle` | Pivot column values |
| `without_detaching` | bool | `sync` | When `true`, behaves like `syncWithoutDetaching` |

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
    // public $standalone = true;

    // Optional. Batch size when processing models (default 100).
    // public $chunkCount = 100;

    public function handle(array $fields, \Illuminate\Support\Collection $models)
    {
        foreach ($models as $model) { /* ... */ }
    }

    public function fields(\Lomkit\Rest\Http\Requests\RestRequest $request): array
    {
        return ['expires_at' => ['required', 'date']];
    }
}
```

Register on the Resource:

```php
public function actions(RestRequest $request): array
{
    return [
        SendWelcomeNotificationAction::make()
            ->withMeta(['color' => '#FFFFFF']),
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

Note the **`fields` payload is an array of `{name, value}` pairs**, not an object. The `search` block resolves the target models (whole `search` schema is supported). Standalone actions (`->standalone()` or `public $standalone = true`) omit `search` and receive an empty collection.

**Field validation follows standard Laravel rules.** The rules declared in `fields()` are evaluated as a normal Laravel validation: presence and cross-field rules (`required`, `required_if`, `present`, …) fire even when a field is absent from the `fields` array. Validation errors are keyed by field name — `fields.{name}` (e.g. `fields.expires_at`) — while an unauthorized field name is reported positionally as `fields.{index}.name`.

**Response**: `{"data": {"impacted": 150}}`.

**Queuing**: implement `Illuminate\Contracts\Queue\ShouldQueue` to dispatch one job per chunk of `$chunkCount` models. Combine with `Lomkit\Rest\Actions\Contracts\BatchableAction` and use `withBatch()` to register `then`/`catch`/`finally` callbacks. Customise `$connection` and `$queue` properties to route jobs.

## Instructions (custom search refinements)

Generate:

```bash
php artisan rest:instruction OddEvenIdInstruction   # → app/Rest/Instructions/
```

```php
class OddEvenIdInstruction extends \Lomkit\Rest\Instructions\Instruction
{
    public function handle(array $fields, \Illuminate\Database\Eloquent\Builder $query)
    {
        $query->whereRaw('MOD(id, 2) = ?', [$fields['type'] === 'odd' ? 1 : 0]);
    }

    public function fields(RestRequest $request): array
    {
        return ['type' => ['required', 'in:odd,even']];
    }

    // Scout variant — implement when this Instruction must also work under full-text search.
    // public function handleScout(array $fields, ScoutBuilder $builder) { ... }
}
```

Register in `Resource::instructions()`. Clients invoke them via the `instructions` key inside `search`:

```json
"instructions": [{"name": "odd-even-id-instruction", "fields": [{"name": "type", "value": "odd"}]}]
```

`->withMeta([...])` is also available on Instructions.

Instruction `fields()` are validated exactly like Action fields: full Laravel validation (including `required` on absent fields), with errors keyed by name under `search.instructions.{index}.fields.{name}`.

## Relationships

All relations must extend `Lomkit\Rest\Relations\Relation` (do **not** use `Illuminate\Database\Eloquent\Relations\*`) and target a **Resource class**, not a model. Supported types: `BelongsTo`, `HasOne`, `HasOneOfMany`, `HasOneThrough`, `MorphOne`, `MorphOneOfMany`, `HasMany`, `HasManyThrough`, `MorphMany`, `BelongsToMany`, `MorphToMany`, `MorphedByMany`, `MorphTo`.

```php
public function relations(RestRequest $request): array
{
    return [
        \Lomkit\Rest\Relations\BelongsTo::make('company', CompanyResource::class)
            ->requiredOnCreation()
            ->prohibitedOnUpdate(),

        \Lomkit\Rest\Relations\BelongsToMany::make('roles', RoleResource::class)
            ->withPivotFields(['created_at'])
            ->withPivotRules(['created_at' => ['required', 'date']]),
    ];
}
```

Mutation constraints (chainable, may take a closure for conditional logic): `requiredOnCreation()`, `prohibitedOnCreation()`, `requiredOnUpdate()`, `prohibitedOnUpdate()`. Many-to-many pivot data is whitelisted by `withPivotFields()` and validated by `withPivotRules()`.

## Delete / restore / force

```
DELETE /api/users          body: {"resources": [5, 6]}      // destroy (soft if SoftDeletes is used)
POST   /api/users/restore  body: {"resources": [5, 6]}      // requires withSoftDeletes()
DELETE /api/users/force    body: {"resources": [5, 6]}      // requires withSoftDeletes()
```

All three return the affected records: `{"data": [{...}], "meta": {"gates": {...}}}`.

## Authorization model

Two layers, both controlled by `config/rest.php`:

### 1. Policy authorization (`authorizations.enabled`, default `true`)

Every model touched by a search/mutate/delete is checked against its Policy. Required policy methods:

| Policy method | Triggered by |
| --- | --- |
| `viewAny` | `search`, `details` (listing) |
| `view` | per-row visibility, includes |
| `create` | `mutate` create |
| `update` | `mutate` update |
| `replicate` | `mutate` create when duplicating from existing data |
| `delete` | `destroy` |
| `restore` | `restore` |
| `forceDelete` | `forceDelete` |
| `attach{Model}($user, $parent, $related)` | attach/sync on a relation (e.g. `attachRole`) |
| `detach{Model}($user, $parent, $related)` | detach/sync on a relation (e.g. `detachRole`) |

**Define a policy for every model you expose**, including the attach/detach methods for many-to-many relations you allow mutating. Missing methods will block the operation.

Results cache for `authorizations.cache.default` minutes (default `5`) per `(resource, identifier, user)`. Override `getAuthorizationCacheKey()` / `cacheAuthorizationFor()` on the resource, or use the `\Lomkit\Rest\Concerns\Resource\DisableAuthorizationsCache` trait, to tweak behaviour. Global toggle: `authorizations.cache.enabled`.

### 2. Gates (`gates.enabled`, default `true`)

When the client passes `"gates": [...]` in `search`, the listed abilities are evaluated per row and attached under the configured `gates` key. The keys returned (`authorized_to_view`, `authorized_to_create`, etc.) are configurable under `rest.gates.names`.

`rest.gates.message.enabled = true` switches the response to `{allowed, message}`, where `message` comes from a `Response::deny('...')` returned from the policy method. Use the `\Lomkit\Rest\Concerns\Resource\DisableGates` trait on a specific resource to opt out without flipping the global config.

## Custom Response

`php artisan rest:response UserResponse` scaffolds a class with a `map($model, $responseModel)` method that returns the array shape sent to the client. Assign on the Resource:

```php
public static $response = \App\Rest\Responses\UserResponse::class;
```

Use sparingly — overriding the response can bypass authorization shaping and field whitelisting if you reference the model directly.

## OpenAPI documentation

Two paths are supported. **Prefer Scramble** for new projects.

### Scramble (recommended)

```bash
composer require dedoc/scramble
```

Add the extension to `config/scramble.php`:

```php
'extensions' => [
    \Lomkit\Rest\Scramble\LomkitLaravelRestApiOperationExtension::class,
],
```

Docs are served at `/docs/api`. Fields, validation rules, relations, search filters, and mutate payloads are inferred automatically from the resources.

### Legacy (built-in)

With `rest.documentation.routing.enabled = true` (default), the package serves a Swagger-style UI at `/api-documentation`. Customise the OpenAPI document with:

```php
\Lomkit\Rest\Facades\Rest::withDocumentationCallback(function (\Lomkit\Rest\Documentation\Schemas\OpenAPI $openAPI) {
    return $openAPI; // mutate as needed
});
```

The legacy path is being superseded by Scramble — don't add new customisation here for greenfield work.

## Precognition

Set `rest.precognition.enabled = true` (default `false`). Clients send `Precognition: true` (and optionally `Precognition-Validate-Only`) on any endpoint, and the request is validated and short-circuited before the controller body runs — `204 No Content` on success, `422` on validation failure. Useful for live frontend form validation.

## Artisan commands shipped by the package

- `rest:resource {Name} --model=Model` — generate a Resource.
- `rest:controller {Name} --resource=ResourceClass` — generate a Controller.
- `rest:base-resource` / `rest:base-controller` — generate the `app/Rest/Resources/Resource.php` (and controller) base classes that all your resources/controllers should extend, with overridable `*Query` hooks.
- `rest:action {Name}` — scaffold an Action.
- `rest:instruction {Name}` — scaffold an Instruction.
- `rest:response {Name}` — scaffold a custom Response class.
- `rest:quick-start` — generates a working UserResource + UsersController and wires `routes/api.php`.

## Working conventions and gotchas

- **Don't write custom controller methods.** If you find yourself wanting to, the right tool is almost always an `Action` (mutation) or an `Instruction` (query). Falling back to a plain Laravel controller bypasses the package's authorization layer.
- **Whitelist by declaration.** Adding a column to the table doesn't expose it — it must be in `fields()`. Same for scopes, relations, actions, instructions.
- **Relation classes are the package's, not Eloquent's.** Use `Lomkit\Rest\Relations\HasMany` (not `Illuminate\Database\Eloquent\Relations\HasMany`), pass `::make('name', TargetResource::class)`, and target the **Resource**, not the model.
- **Soft deletes are opt-in at the route.** Even if the model uses `SoftDeletes`, `restore` / `force` routes only register when `->withSoftDeletes()` is chained onto `Rest::resource(...)`.
- **Don't bypass policies by toggling `authorizations.enabled = false`** — write the policy. The package's threat model assumes it's on, including `viewAny`, `replicate`, and `attach{Model}`/`detach{Model}`.
- **`mutate` is one batch but per-item-authorized.** A single denied item fails the whole batch.
- **`includes` are not recursive** — they accept `filters`/`sorts`/`scopes`/`limit`/`selects` but not nested `includes`. To go deeper, register chained relations on the intermediate resource and add an entry per level.
- **Action `fields` payload is an array of `{name, value}` pairs**, not an object — easy mistake.
- **`HasOneThrough` / `HasManyThrough` can be searched/included but not mutated.**
- **Cache invalidation.** The authorization cache is keyed per user and per resource; when debugging policy changes, disable the cache via config or use the `DisableAuthorizationsCache` trait.

## Pointers into the codebase

- Service provider & routing — `src/RestServiceProvider.php`, `src/Rest.php`, `src/Http/Routing/{ResourceRegistrar,PendingResourceRegistration}.php`
- Request classes per verb — `src/Http/Requests/{Search,Mutate,Operate,Destroy,Restore,ForceDestroy,Details}Request.php`
- Resource base class & traits — `src/Http/Resource.php`, `src/Concerns/Resource/*` (`DisableGates`, `DisableAuthorizationsCache`, hooks, ...)
- Query layer — `src/Query/Builder.php`, `src/Query/ScoutBuilder.php`
- Actions / Instructions — `src/Actions/{Action,Actionable}.php`, `src/Instructions/{Instruction,Instructionable}.php`
- Relations — `src/Relations/*.php`
- Validation rules used to validate the request bodies — `src/Rules/{Search,Mutate,Operate,Resource}/*`
- Scramble extension — `src/Scramble/LomkitLaravelRestApiOperationExtension.php`
- Generator stubs — `src/Console/stubs/*.stub`
