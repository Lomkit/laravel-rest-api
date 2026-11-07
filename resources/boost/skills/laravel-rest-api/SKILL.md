---
name: laravel-rest-api
description: Use this skill when working with the `lomkit/laravel-rest-api` package — building or modifying Resources, Controllers, Actions, Metrics, Instructions, registering REST routes via `Rest::resource()`, or writing client payloads for the `search`, `mutate`, `operate`, `metric`, `details`, `destroy`, `restore` and `force` endpoints. Triggers include the `Lomkit\Rest\` namespace, the `Rest` facade, the `rest:*` artisan commands, and `app/Rest/Resources` / `app/Rest/Controllers` directories.
---

# Laravel Rest API (lomkit/laravel-rest-api)

`lomkit/laravel-rest-api` exposes Eloquent models through a small, fixed set of POST-driven endpoints. Instead of writing one controller per use case, you declare a **Resource** (what is exposed and how it can be queried/mutated) and a thin **Controller** that binds the resource to a route prefix. The package wires CRUD, search, batch mutations, custom actions, soft-delete handling, policy-based authorization and OpenAPI documentation on top of that pair.

Requirements: PHP 8.3+, Laravel 13.30+.

Official documentation: https://laravel-rest-api.lomkit.com/

## When to use this skill

Use this skill when working with the `lomkit/laravel-rest-api` package — building or modifying Resources, Controllers, Actions, Metrics, Instructions, registering REST routes via `Rest::resource()`, or writing client payloads for the `search`, `mutate`, `operate`, `metric`, `details`, `destroy`, `restore` and `force` endpoints. Triggers include the `Lomkit\Rest\` namespace, the `Rest` facade, the `rest:*` artisan commands, and `app/Rest/Resources` / `app/Rest/Controllers` directories.

## Features

- **Resource**: declares the shape of an Eloquent model's API (fields, relations, scopes, validation, lifecycle hooks). Generate with:

  ```bash
  php artisan rest:resource UserResource --model=User
  ```

- **Controller**: a thin binding between a route prefix and a Resource (`$resource`); it has no hooks: endpoint events live on the resource. Generate with:

  ```bash
  php artisan rest:controller UsersController --resource=UserResource
  ```

- **Routing**: register all REST endpoints (`details`, `search`, `mutate`, `operate`, `metric`, `destroy`, optional `restore`/`force`) for a model with a single call:

  ```php
  Rest::resource('users', \App\Rest\Controllers\UsersController::class);
  ```

- **Search**: filterable, sortable, paginated reads with nested relation includes, aggregates, scopes, per-row policy gates and an opt-in text search (`LIKE`, the full-text index of the database, or a Laravel Scout search engine) via `POST /{resource}/search`.
- **Mutate**: batched create/update plus relation operations (`attach`, `detach`, `sync`, `toggle`) in one request via `POST /{resource}/mutate`, which can return the written rows (`response`).
- **Actions**: custom mutators exposed under `/{resource}/actions/{uriKey}`, runnable on the models that `search` matches, on an explicit list of ids, or on no model (`ActionTarget`). Generate with:

  ```bash
  php artisan rest:action SendWelcomeNotificationAction
  ```

- **Instructions**: custom query refinements invoked from the `search` payload's `instructions` key. Generate with:

  ```bash
  php artisan rest:instruction OddEvenIdInstruction
  ```

- **Soft-delete endpoints**: opt-in `restore` and `force` routes via `->withSoftDeletes()` chained on `Rest::resource(...)`.
- **Authorization**: automatic per-model Policy checks (`viewAny`, `view`, `create`, `update`, `delete`, `restore`, `forceDelete`, and the link abilities a relation declares) plus client-requested per-row `gates`, with the abilities that a resource declares in `gates()`. Class-level checks (`viewAny` of every reached resource, `create`) run before validation, so a denied check answers `403` before any `422`. Row checks of `mutate` run in one batch before the first write.
- **Custom Response**: override the output shape per Resource via `public static string $response = UserResponse::class;`. Generate with:

  ```bash
  php artisan rest:response UserResponse
  ```

- **OpenAPI documentation**: native Scramble extension (`LomkitLaravelRestApiOperationExtension`) infers docs from Resources.
- **Precognition**: live form validation (`Precognition: true` header) short-circuits the request after validation when `rest.precognition.enabled = true`.
- **Request size limits**: each list of a request accepts a limited number of entries, filters nest up to 1 level and includes and relation operations up to 3 levels, two totals bound one search and one request, and a pattern of `like` or `ilike` takes a limited number of characters, all set in `rest.limits` (`config/rest.php`): `sizes` for the lists, `depths` for the nesting of filters, includes and relations, `totals` for the two totals, `lengths` for the patterns. A pattern over its length is a `422` on its value. A list over its limit is one `422` on that list.

## If the package is installed

- **Default to extending it** for new endpoints — generate a `Resource` + `Controller` and register with `Rest::resource(...)` rather than adding a plain Laravel controller for the same model.
- **Match existing patterns** in `app/Rest/` — base classes (`app/Rest/Resources/Resource.php`), naming, hook overrides, and the project's `perimeterQuery` conventions usually carry tenant or visibility constraints that you must preserve.
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
php artisan rest:quick-start                     # optional: asks which models to expose, then writes their resources, controllers, policies that allow every ability and routes behind auth:sanctum (or --guard)
```

`rest:quick-start` protects its routes with the `sanctum` guard, or with `--guard=<name>`: `api` means Laravel Passport when `config/auth.php` does not define it or gives it the `passport` driver, any other guard of `config/auth.php` is used as it is, and an undefined guard stops the command before any write. It runs `php artisan install:api` (Laravel Sanctum), or `install:api --passport` for Laravel Passport, when `routes/api.php` is missing, or when it adds routes and the package of the guard is missing (no `sanctum` guard, no `Laravel\Passport\Passport` class), after a confirmation or with `--install-api`. With another guard and no `routes/api.php`, it runs `install:api`, which also installs Laravel Sanctum. When an install is needed and `--guard` is not passed, it first asks `Which guard should protect the API routes?` (default `sanctum`), also with `--install-api`, which only skips the install confirmation: pass `--guard` to skip the guard question. After `install:api --passport`, define the `api` guard with the `passport` driver in `config/auth.php`: the command says so and never edits that file. With an existing `routes/api.php`, `install:api` keeps the file and prints `API routes file already exists.`, and the run goes on when it exits 0. Without interaction, name the models: `php artisan rest:quick-start -n --model=Post --model=Tag --install-api`. A related model that is not chosen is skipped, with the `--model` option to add. Without a terminal (CI, scripts, agents), pass `--model` and `--install-api` too: the command never answers its own questions. It stops before any write when the routes are cached (run `php artisan route:clear` first), when existing routes already use the URIs or names of the new ones (for example `Route::apiResource('posts', ...)`), or when the routes it adds for two chosen models would get the same resource name (for example `App\Models\Note` and `App\Models\Archive\Note`, both `notes`): expose them in separate runs, or rename one. It also stops before any write when Gate would not discover the policy it generates for a model, such as a model outside the application namespace: register that model's policy with `Gate::policy()` first. A run that adds no route reads the guard of its outro from the `->middleware('auth:<guard>')` of the existing routes, or uses `sanctum`. The command keeps existing files, never edits the user model (add the `HasApiTokens` trait of Laravel Sanctum or Laravel Passport yourself), checks the routes and policies in a fresh process and exits 1 when something blocks. The generated policies let signed-in users do everything: edit them before production.

The `RestServiceProvider` and `Rest` facade are auto-registered via composer's `extra.laravel`.

## Core concepts

### 1. Resource — `app/Rest/Resources/{Name}Resource.php`

A Resource declares the **shape of what the API exposes** for an Eloquent model. Generate one with:

```bash
php artisan rest:resource UserResource --model=User
php artisan rest:resource UserResource --model=User --generate   # one field per column of the users table, plus the relations that have a resource
```

Override these methods. They take no argument: a list that depends on the user reads `$this->request()->user()`. `fields()`, `relations()`, `scopes()`, `limits()`, `actions()`, `instructions()`, `text()` and `gates()` run once per request, the first time the package reads them, and the resource keeps nothing between requests:

- `fields()` — list of typed `Lomkit\Rest\Fields\*` objects the client may select / filter / sort / mutate on: `ID`, `Text`, `Number`, `Integer`, `Boolean`, `Date`, `DateTime`, `Enum`, `Json`, or an app type extending `Field`. Strings and duplicate names throw `InvalidFieldException`. `$this->definition()->fields()` returns a `FieldSet`; `->names()` gives the list of names.

  ```php
  use Lomkit\Rest\Fields\{ID, Integer, Text};

  public function fields(): array
  {
      return [ID::make('id'), Text::make('name'), Integer::make('price'), Text::make('note')->nullable()];
  }
  ```

  How a search reads a field depends on its declaration:

  ```php
  use Illuminate\Contracts\Database\Eloquent\Builder;

  ID::make('id')->sortable(false),                     // returned, 422 in sorts and aggregates
  Text::make('api_token')->writeOnly(),                // never returned, 422 in selects, sorts, filters and aggregates
  DateTime::make('created_at')->selectable(false),     // not returned, 422 in selects, sortable
  Json::make('settings'),                              // returned, 422 in sorts, filters, min/max/avg/sum
  Text::make('summary')->nullable(),                   // the Text operators, plus is null and is not null
  Text::make('slug')->filterable(false),               // 422 in filters
  Text::make('full_name')
      ->virtual()                                      // not returned, 422 in selects and aggregates
      ->sortUsing(fn (Builder $query, string $direction) => $query
          ->orderBy('last_name', $direction)
          ->orderBy('first_name', $direction))
      ->filterable(['=', 'like'])                      // these operators only
      ->filterUsing(fn (Builder $query, string $operator, mixed $value) => $query
          ->where('first_name', $operator, $value)
          ->orWhere('last_name', $operator, $value)),
  ```

  - Selectable fields (not `writeOnly()`, not `selectable(false)`, and not `virtual()` unless it declares `resolveUsing()`) are the only ones `selects` accepts and the ones a row returns without `selects`, at every include level. `resolveUsing(fn (Model $model): mixed => ...)` gives a field its value: the callback receives the model of the row, runs once per returned row, and its value is sent as returned (a `Json` field does not decode it). `requires(['first_name', 'last_name'])` lists the columns the callback reads; they load when the field is returned. A field that is not a column must be `virtual()`, and `$appends` never reach a row: return an appended value through `resolveUsing()`.
  - Sortable fields are the only ones `sorts` accepts: `Json`, `sortable(false)`, `writeOnly()` fields and `virtual()` fields without `sortUsing()` are refused. `sortUsing()` replaces the column order of its field (and makes a virtual or write-only field sortable); its callback receives the Eloquent query, on an include as well, and `asc` or `desc`. The other fields order by their qualified column (`posts.views`).
  - Filterable fields are the only ones `filters` accepts: `Json`, `filterable(false)` fields, and `virtual()` or `writeOnly()` fields without `filterUsing()` are refused (`The selected search.filters.0.field is invalid.`). The operators come from the field type, `nullable()` adds `is null` and `is not null`, and `filterable([...])` narrows them (an operator the type does not have throws `InvalidFieldException`). `filterUsing()`, a closure or a `Lomkit\Rest\Contracts\Filter` object, replaces the column condition of its field, also at the end of a relation path (`author.full_name`): it receives the Eloquent query, the operator as a string and the value as sent, and its conditions stay in their own group. The other fields compare their qualified column (`posts.views`, or the pivot table for a pivot field).
  - Searchable fields (`searchable()`) are the ones a text search reads, see "Text search" below. Only `Text` fields (and app types extending `Text`) can be searchable; `searchable()` on another type, a `virtual()` or a `writeOnly()` field throws `InvalidFieldException`, in any order of the calls.

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

  `label()` names a field in validation messages, and `messages()` replaces Laravel's text for its rules, keyed by rule name (a rule object by its snake class name, such as `enum`). They apply to `mutate` attributes at every level, pivot fields, action and instruction fields and scope parameters; error keys do not change. The package uses them as given: pass `__()` to translate them. A key is named by the request's `attributes()`, then the field's `label()`, a `validation.attributes` line of the full key or of a pattern, for an action or instruction field `validation.attributes.{name}` or else its name in snake case with spaces, and then, for a key the package parses, the key as written. A rule's text comes from the request's `messages()` for the key and the rule (`mutate.*.attributes.email.unique`), then the field's `messages()`, a request message keyed by the rule or the key alone, a `validation.custom` line and Laravel's line. For a rule object (an `Enum` field's rule, a `ValidationRule` class, a closure), a request message keyed by the key alone, the class name or `{key}.{class name}` comes before the field's message, and no `validation.custom` line applies.

  ```php
  Text::make('email')
      ->label(__('email address'))                     // "The email address field is required."
      ->creationRules('required', 'unique:users,email')
      ->messages(['unique' => __('This :attribute is already registered.')]),
  ```
- `relations()` — array of `Lomkit\Rest\Relations\*` instances declared with `::make('relationName', TargetResource::class)`. Only declared relations are includable/mutatable.
- `scopes()` — `Lomkit\Rest\Scopes\Scope` objects naming the model's local scopes (`scopeX()` methods or `#[Scope]` methods) the client may invoke. Builder methods and macros such as `withTrashed` cannot be called as scopes. `fields()` declares the scope parameters, in the order of the scope method's arguments; strings throw `InvalidScopeException`.

  ```php
  use Lomkit\Rest\Fields\{Date, Integer};
  use Lomkit\Rest\Scopes\Scope;

  public function scopes(): array
  {
      return [
          Scope::make('publishedSince')->fields([Date::make('since')]),           // scopePublishedSince(Builder $query, CarbonImmutable $since)
          Scope::make('popular')
              ->fields([Integer::make('min_views')->default(100)])
              ->meta(['label' => 'Popular']),
      ];
  }
  ```
- `limits()` — allowed `limit` values (default `[10, 25, 50]`). The `$defaultLimit` property (`50`) sizes a page sent without `limit`, and `$includeLimit` (`10`) the rows of each parent row in an include of this resource sent without `limit`. Both must be values of `limits()`, or the resource throws `InvalidResourceException` when its limits are first read.
- `actions()` — `Lomkit\Rest\Actions\Action` instances, each with its own route `/actions/{uriKey}`; read when the routes register, so keep the list the same for every user.
- `instructions()` — `Lomkit\Rest\Instructions\Instruction` instances exposed under the `instructions` search key.
- `text(): ?\Lomkit\Rest\Text\TextSearch` — the text search strategy of `search.text` (`Contains::make()`, `FullText::make()` or `ScoutEngine::make()`), `null` by default (no text search). See "Text search" and "Search engines" below.
- `gates(): array` — the names of extra policy methods that `search.gates` accepts next to the built-in abilities, `[]` by default. See "2. Gates" below.
- `alwaysSelect(): array` — the columns of the table that every search of the resource loads without returning them (`[]` by default): the columns that a policy, an accessor, an observer, a hook or `map()` reads.
- `rules(): array`, `createRules(): array`, `updateRules(): array` — resource-level rules applied during mutate, for rules that compare attributes and for nested keys (`metadata.source`). Put the rules of one attribute on its field instead. Each key gets one list: the field rules, then `createRules()` (on `create`) or `updateRules()` (on `update`, `sync`, `toggle`), then `rules()`; pipe strings are split first. The three methods are read once per request, so every entry shares the rule objects they return. For a rule that depends on the entry, use `\Lomkit\Rest\Mutations\Contextual::rule(fn (MutationContext $mutation) => Rule::unique(Post::class, 'title')->ignore($mutation->key))` or a rule object that implements `\Lomkit\Rest\Mutations\MutationAwareRule` (`setMutation(MutationContext $mutation): static`, called on a clone per entry); `MutationContext` holds the `operation`, `key` and `depth` of the entry. A `Rule::when()` condition reads the attributes of its entry. The same holds for field rules.
- `defaultOrderBy()` — default sort (defaults to the model key in descending order, `[$model->getKeyName() => 'desc']`); a column may name its table (`posts.views`).
- Resource hooks (model-lifecycle), inside the endpoint's transaction: `mutating(MutationNode $entry, Model $model): void` and `mutated()` receive the entry of `payload()` (`operation`, `key`, `attributes`, `pivot`, `withoutDetaching`, `relations`, `type`); `destroying(Model $model): void`, `destroyed`, `restoring`, `restored`, `forceDestroying`, `forceDestroyed` receive the row. They take no request: read it with `$this->request()`. `mutating`/`mutated` do not run for an entry that writes no attribute (a link-only target).
- Endpoint events (one per endpoint moment, typed payload, return `void`): `describing()`, `described(array $details)`, `searching(SearchContext $search)` (also for the search of an action or a metric; throw `ValidationException` for a 422 before any query), `searched(LengthAwarePaginator $page)`, `mutatingAll(array $entries)` (`MutationNode` list) and `mutatedAll(array $operations)` inside the mutate transaction, `operating(Action $action, ActionFields $fields)` and `operated(Action $action, ActionFields $fields, int $impacted)` around the chunks (inside an `atomic()` transaction; `respond()` after), `destroyingAll(Collection $models)` / `destroyedAll()` and the `restoring`/`forceDestroying` twins inside the deletion transaction. They run after the entry checks and validation, the events that receive rows after the row checks; a throw inside a transaction rolls it back. Read the request with `$this->request()`.

A model that uses `Laravel\Scout\Searchable` gives its resource no text search by itself: declare `text()` with `ScoutEngine::make()`, see "Search engines" below.

The Resource also has typed static properties (PHP requires the `string` type when you redeclare them):
- `public static string $model = Post::class;` — the Eloquent class (required: a resource without it throws `InvalidResourceException`). Read it with `PostResource::modelClass()`.
- `public static string $response = \Lomkit\Rest\Http\Response::class;` — override with a subclass of `Lomkit\Rest\Http\Response` to customise output (see "Custom Response").

Resources extending `app/Rest/Resources/Resource.php` (generated via `rest:base-resource`) may override:
- `perimeterQuery` — the rows that exist for the client (tenant scoping, soft-visibility, etc.), on every endpoint: apply the constraints to `$query` itself and return it; the package ignores the returned builder. It applies to searches and actions, at every level (a text search on a search engine takes its rows from the index perimeter instead), and limits the keys that `mutate` accepts, at the root and under relations, and the keys of the deletion endpoints: they are checked during validation, with one query per resource class, a key it leaves out is a `422` (`The selected mutate.0.key is invalid.`), and the rows it returns are the rows that the write changes. It reads the endpoint's request with `$this->request()`: branch on `$this->request()->endpoint()` to let one endpoint reach rows that the others leave out. A row the client may see but not change is a policy matter (`403`), not a perimeter one.

  ```php
  use Illuminate\Contracts\Database\Eloquent\Builder;

  public function perimeterQuery(Builder $query): Builder
  {
      return $query->where('team_id', $this->request()->user()->team_id);
  }
  ```
- `performDelete(Model $model): void`, `performRestore(Model $model): void`, `performForceDelete(Model $model): void` — override the actual operation on a model.

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

Controllers have no hooks. Put request-level code in the resource's endpoint events (above): `searching(SearchContext $search)` refuses a search with a `ValidationException` (a 422): `$search->filtersOn()`, `sortsOn()` (a field, a relation path such as `author.last_name`, or an aggregate key), `scopes()` and `includes()` read the validated search, nested groups flattened and relation group filters prefixed (`posts.status`, with `exists`). Editing the request input in an event changes nothing.

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

`Rest::resource()` returns a builder with these options. Endpoint keys are `details`, `search`, `mutate`, `operate`, `metric`, `destroy`, `restore`, `forceDestroy` and `batch` (or the `Lomkit\Rest\Http\Routing\Endpoint` cases); an unknown key throws. The result does not depend on the call order.

```php
Rest::resource('posts', PostsController::class)
    ->withSoftDeletes('restore')                 // restore and/or forceDestroy; none = both
    ->withBatchProgress()                        // GET /posts/actions/{action}/batches/{batch}
    ->except('destroy')                          // or ->only('details', 'search')
    ->names('blog.posts')                        // name prefix; ->names([...]) or ->name('search', 'posts.find') for exact names
    ->middleware('verified')                     // added after the package middleware
    ->middlewareFor('operate', 'throttle:5,1')   // added after the resource middleware, before each action's own
    ->withoutMiddleware('verified')              // or ->withoutMiddlewareFor('details', 'verified')
    ->register();                                // optional: routes register when the builder is released
```

`Rest::resource()` registers these routes. `Route::resourceVerbs()` changes the verbs through the keys `search`, `mutate`, `actions`, `metrics`, `restore` and `forceDestroy` (each must be one URI segment):

| Method | URI | Endpoint | Purpose |
| --- | --- | --- | --- |
| `GET` | `/users` | `details` | Schema for the resource (fields, relations, scopes, limits, actions, metrics, instructions, rules, text search, gates) |
| `POST` | `/users/search` | `search` | Filterable/sortable/paginated read |
| `POST` | `/users/mutate` | `mutate` | Batch create/update + relation operations |
| `POST` | `/users/actions/{uriKey}` | `operate` | Run a custom action: one route per action, named `{name}.operate.{uriKey}`, with the action's own middleware |
| `POST` | `/users/actions/{action}` | `operate` | Unknown keys: `403` without `viewAny`, else `404` (named `{name}.operate`) |
| `POST` | `/users/metrics/{uriKey}` | `metric` | Compute a metric: one route per metric, named `{name}.metric.{uriKey}`, with the metric's own middleware |
| `POST` | `/users/metrics/{metric}` | `metric` | Unknown keys: `403` without `viewAny`, else `404` (named `{name}.metric`) |
| `DELETE` | `/users` | `destroy` | Batch (soft-)delete |
| `POST` | `/users/restore` | `restore` | Opt-in via `withSoftDeletes()` |
| `DELETE` | `/users/force` | `forceDestroy` | Opt-in via `withSoftDeletes()` |
| `GET` | `/users/actions/{action}/batches/{batch}` | `batch` | Opt-in via `withBatchProgress()`: progress of a batched action |

Every route carries the `EnforceExpectsJson` middleware, which adds `application/json` to the `Accept` header, so errors come back as JSON. Group middleware runs before it. Route names only have to be unique within one resource; two resources with the same name or URI still overwrite each other, as with Laravel routes. Laravel Precognition is supported when `rest.precognition.enabled = true` — clients send `Precognition: true` and the request returns `204` after validation without touching the controller body, or `422` with errors. The checks made before validation still run, so a denied check answers `403` first.

## `details` endpoint

`GET /api/artworks` describes the resource. It lists what the declarations of the resource (`fields()`, `relations()`, `scopes()`, `actions()`, `metrics()`, `instructions()`, the rules methods, `text()` and `gates()`) return for the current request, which can depend on `$this->request()->user()`. It checks `viewAny` only and applies no policy to the entries. With a `collector` morph-map alias:

```json
{"data": {
  "actions": [{"name": "Retire Artworks", "uriKey": "retire-artworks", "target": "search",
    "fields": [{"name": "notify", "label": null, "type": "boolean", "nullable": false, "default": false, "rules": ["boolean"], "meta": {}}],
    "meta": {}, "destructive": false, "confirm": null}],
  "metrics": [{"name": "Artworks By Medium", "uriKey": "artworks-by-medium", "type": "partition", "ranges": [], "meta": {}}],
  "instructions": [{"name": "By Medium", "uriKey": "by-medium",
    "fields": [{"name": "medium", "label": null, "type": "enum", "nullable": false, "default": null, "rules": ["enum", "required"], "meta": {}, "values": ["oil", "ink", "watercolor"]}],
    "meta": {}, "text": true}],
  "fields": [
    {"name": "id", "label": null, "type": "id", "nullable": false, "select": true, "sort": true, "filter": {"operators": ["=", "!=", "in", "not in"]}, "create": false, "update": false, "rules": {"all": ["integer"], "create": [], "update": []}, "meta": {}},
    {"name": "title", "label": "Title", "type": "text", "nullable": false, "select": true, "sort": true, "filter": {"operators": ["=", "!=", "like", "not like", "ilike", "not ilike", "in", "not in"]}, "create": true, "update": true, "rules": {"all": ["string", "max:120"], "create": ["required"], "update": ["filled"]}, "meta": {}},
    {"name": "medium", "label": null, "type": "enum", "nullable": false, "select": true, "sort": true, "filter": {"operators": ["=", "!=", "in", "not in"]}, "create": true, "update": true, "rules": {"all": ["enum"], "create": [], "update": []}, "meta": {}, "values": ["oil", "ink", "watercolor"]},
    {"name": "dimensions", "label": null, "type": "json", "nullable": true, "select": true, "sort": false, "filter": {"operators": []}, "create": true, "update": true, "rules": {"all": ["nullable", "array"], "create": [], "update": []}, "meta": {}},
    {"name": "price", "label": null, "type": "number", "nullable": true, "select": true, "sort": true, "filter": {"operators": ["=", "!=", ">", ">=", "<", "<=", "between", "not between", "in", "not in", "is null", "is not null"]}, "create": true, "update": false, "rules": {"all": ["nullable", "numeric"], "create": [], "update": []}, "meta": {}},
    {"name": "notes", "label": null, "type": "text", "nullable": true, "select": false, "sort": false, "filter": {"operators": []}, "create": true, "update": true, "rules": {"all": ["nullable", "string"], "create": [], "update": []}, "meta": {}}],
  "limits": [10, 25, 50],
  "scopes": [{"name": "pricedUnder", "fields": [{"name": "max", "label": null, "type": "number", "nullable": false, "default": null, "rules": ["numeric"], "meta": {}}], "meta": {}}],
  "relations": [
    {"resource": "App\\Rest\\Resources\\ArtistResource", "relation": "artist",
     "constraints": {"required_on_creation": false, "prohibited_on_creation": false, "required_on_update": false, "prohibited_on_update": false}, "name": "BelongsTo"},
    {"resource": "App\\Rest\\Resources\\ExhibitionResource", "relation": "exhibitions",
     "constraints": {"required_on_creation": false, "prohibited_on_creation": false, "required_on_update": false, "prohibited_on_update": false}, "name": "BelongsToMany",
     "pivot_fields": [
       {"name": "position", "label": null, "type": "integer", "nullable": true, "select": true, "sort": false, "filter": {"operators": ["=", "!=", ">", ">=", "<", "<=", "between", "not between", "in", "not in", "is null", "is not null"]}, "create": true, "update": true, "rules": {"all": ["nullable", "integer"], "create": [], "update": []}, "meta": {}},
       {"name": "wall", "label": null, "type": "text", "nullable": false, "select": true, "sort": false, "filter": {"operators": ["=", "!=", "like", "not like", "ilike", "not ilike", "in", "not in"]}, "create": true, "update": false, "rules": {"all": ["string"], "create": [], "update": []}, "meta": {}}]},
    {"resource": "App\\Rest\\Resources\\CollectorResource", "relation": "owner",
     "constraints": {"required_on_creation": false, "prohibited_on_creation": false, "required_on_update": false, "prohibited_on_update": false}, "name": "MorphTo",
     "types": {"collector": "App\\Rest\\Resources\\CollectorResource"},
     "morph": {"type_column": "owner_type", "key_column": "owner_id"}}],
  "rules": {"all": {}, "create": {"price": ["required", "min:0"]}, "update": {}},
  "text": {"fields": ["title"], "modes": ["words", "phrase"], "relevance": false, "trashed": false, "max_length": 255, "engine": null},
  "gates": ["view", "create", "update", "delete", "restore", "forceDelete"]
}}
```

- `fields` has one entry per field, in declared order. `label` is the field's `label()`, or `null` without one, in field, pivot field and input entries. `select`: accepted in `search.selects` and returned. `sort`: accepted in `search.sorts`. `filter.operators`: the operators of `search.filters` on the field, `[]` when it is not filterable. `create` / `update`: writable on that operation. `rules.all` is the type rule, `nullable` and `rules()`; `rules.create` and `rules.update` are `creationRules()` and `updateRules()`. `Enum` entries add `values`. There is no `default`: resource fields do not apply it.
- `rules` holds the resource rules (`rules()`, `createRules()`, `updateRules()`), keyed by attribute, as lists of strings.
- `relations`: `resource` is the class name of the related resource (absent on a `MorphTo` with several targets), and `name` is the short class name of the relation (or its `$name`). Many-to-many relations (every `PivotRelation`) add `pivot_fields`: `create` means `create` and `attach`, `update` means `update`, `sync` and `toggle`, `select` means returned in the `pivot` of an included row, `filter.operators` applies to `<relation>.pivot.<field>` filters, and `sort` is always `false`. When the model method is not an Eloquent many-to-many relation, `select` is `false` and `filter.operators` is `[]`. `MorphTo` adds `types` (each morph type, the morph-map alias or the model class, mapped to its resource class) and `morph` (`type_column`, `key_column`). Detect `pivot_fields`, `types` and `morph` by their presence, not by `name`.
- `text` is `null` without `text()`. Otherwise: `fields` (the searchable fields, `[]` on `ScoutEngine`), `modes`, `relevance` (`@relevance` accepted with a text), `trashed` (`search.trashed` accepted with a text), `max_length`, and `engine`, `null` on `Contains` and `FullText`. On `ScoutEngine`, `engine` holds `filters` (the `filterable()` attributes with `name`, `type`, `operators`, and `values` for an `Enum`; never the perimeter attributes), `sorts`, `max_sorts`, `max_page_size` and `window` (`null` when unbounded). With a filled text on `ScoutEngine`, `text.engine` replaces the `sort` and `filter` of the field entries, within the grammar of [Search engines](#search-engines).
- `gates` lists the abilities that `search.gates` accepts: `view`, `create`, `update`, `delete`, `restore`, `forceDelete`, then the names that `gates()` declares. It is `[]` when `isGatingEnabled()` returns `false`.
- `actions`, `instructions` and `relations` are lists, also when a declaration method returns a keyed array. Maps are objects, also when empty (`"meta": {}`, `"all": {}`). Ignore unknown keys: later releases can add keys.
- A declaration error also throws on `details`: `InvalidTextSearchException` for the text search, `InvalidResourceException` for a `MorphTo` whose model method is not a `MorphTo` of that name or whose targets are not a list of distinct resources, or an invalid name in `gates()`, `InvalidFieldException` for an `Enum` field without an enum class or cast, `BadMethodCallException` for a many-to-many relation or a `MorphTo` without its model method.

The `fields` of actions, instructions and scopes take `rules()` only: declaring `creationRules()` or `updateRules()` on them throws `InvalidFieldException` when the fields are built. They are described as input entries, which a frontend can render as a form:

```json
{"name": "since", "label": null, "type": "date", "nullable": false, "default": null, "rules": ["date"], "meta": {}}
```

`Enum` entries add `values`. Rules are strings: pipe strings are split, `Stringable` rules are cast to string, other rule objects are named by their snake class name (`enum`, `password`), and closures and anonymous rule objects are left out.

An instruction's `text` says whether it can be sent next to a `search.text` of this resource: `false` without a `text()` strategy; with `Contains` or `FullText`, `true` unless the instruction only applies to search engines; with `ScoutEngine`, `true` when it implements `AppliesToEngine`.

Frontends typically call `details` once to drive form rendering, then `search`/`mutate` based on it.

## `search` payload

`POST /api/users/search` (every key is optional):

```json
{
  "search": {
    "text":   {"value": "needle", "mode": "phrase"},
    "scopes": [{"name": "publishedSince", "parameters": ["2026-01-01"]}],
    "filters": [
      {"field": "id", "operator": "in", "value": [1, 2], "type": "or"},
      {"nested": [
        {"field": "posts.views", "operator": ">", "value": 2},
        {"field": "name", "operator": "like", "value": "Ada%", "type": "or"}
      ]},
      {"relation": "posts", "exists": false, "nested": [{"field": "published", "value": false}]}
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

**Keys and shapes**: a key the package does not know inside a search object is a `422` on that key, named as written (`The search.filters.0.operater field is prohibited.`); every list of a search (`filters`, `nested`, `sorts`, `selects`, `scopes`, `aggregates`, `includes`, `instructions`, `gates`) must be a JSON list, and an object is a `422` on the list (`The search.filters field must be a list.`).
**Filter `field`** must reach a filterable field: a field of the resource, a dotted path through declared relations checked on the resource at its end (`posts.comments.body`), or a filterable pivot field right after a `BelongsToMany`, `MorphToMany` or `MorphedByMany` relation (`roles.pivot.expires_at`). Through a `MorphTo` relation with one target (`commentable.title`), the field is read on the declared target resource, and only the rows of that target's type match: other stored types never match nor fail. Through a `MorphTo` with several targets, a dotted path is a `422`: use a relation group with `types`. The `perimeterQuery()` of the resource of each relation on a path (the declared target for a `MorphTo`) applies to its related rows, each in its own group, and each resource on the path needs `viewAny`. Another field is a `422` under `search.filters.{index}.field`.
**Filter `operator`** depends on the field type (`Lomkit\Rest\Fields\Filtering\FilterOperator`): `ID` and `Enum` take `=`, `!=`, `in`, `not in`; `Text` takes `=`, `!=`, `like`, `not like`, `ilike`, `not ilike`, `in`, `not in`; `Number` and `Integer` take `=`, `!=`, `>`, `>=`, `<`, `<=`, `between`, `not between`, `in`, `not in`; `Boolean` takes `=`, `!=`; `Date` and `DateTime` take `=`, `!=`, `>`, `>=`, `<`, `<=`, `between`, `not between`; `Json` takes none. `nullable()` adds `is null` and `is not null`, and `filterable([...])` narrows the list. Without `operator` a filter uses `=`, and `operator` is required on a field that does not accept `=`. Another operator is a `422` under `search.filters.{index}.operator`.
**Filter `value`** is validated per operator: a value of the field type for `=`, `!=`, `>`, `>=`, `<`, `<=`; a string of at most `rest.limits.lengths.patterns` characters (default `255`) for `like`, `not like`, `ilike`, `not ilike`; a non-empty list of at most `rest.limits.sizes.values` values (default `100`) for `in` and `not in`; exactly two values for `between` and `not between`; no value for `is null` and `is not null`. `=` or `!=` with `null` is a `422`: use `is null` or `is not null`. `like` is case-sensitive on PostgreSQL, follows the column collation on MySQL and ignores the case of ASCII letters on SQLite; `ilike` ignores case, except on MySQL columns with a binary or case-sensitive collation, and only for ASCII letters on SQLite.
**Filter `type`**: `and` (default) or `or`. Use `nested` to group filters with their own logical operator, up to `rest.limits.depths.filters` levels (default `1`; each `nested` key is one level); a deeper `nested` is a `422` on its key, and nothing below it is validated. With `Contains` and `FullText`, filters keep this whole grammar next to `text`. With `ScoutEngine` and a filled text, filters take its `filterable()` attributes, `and` only, without `nested`, relation paths or relation groups (see "Search engines").
**Relation groups** (`{"relation": "posts", "exists": false, "nested": [{"field": "status", "value": "draft"}]}`) filter on the related records of one relation. With `exists` `true` (default), the record needs at least one related record that matches every filter of `nested`. With `false`, it needs none. Without `nested`, or with `[]`, any related record counts. One group applies all its filters to the same related record, where two dotted filters (`posts.status`, `posts.featured`) can match two different posts. A dotted filter keeps its meaning (`posts.status` `!=` `draft`: has a post that is not a draft). `nested` takes the fields of the related resource, with `type`, groups, relation paths and relation groups. The related resource's `perimeterQuery()` applies in its own group, and the related resource needs `viewAny`. The keys are `relation`, `exists`, `type` and `nested`. Another key next to `relation` is a `422` on that key. `relation` must name one relation of the resource (a dotted path is a `422`): reach a deeper relation with a relation path in `nested` (`{"field": "comments.body", ...}`), or with a relation group there when `rest.limits.depths.filters` is `2` or more. `exists` must be `true` or `false`. Its `nested` is one level of `rest.limits.depths.filters` (a group without `nested`, or with `[]`, fits at any depth), takes `sizes.filters` and counts in `totals.filters`. Through a `MorphTo`, the group takes `types`, one filter list per morph type (`{"relation": "labelable", "types": {"book": [...]}}`); `nested` is a `422` with several targets; `exists: false` also keeps the rows of other types and without a type. A pivot field of the group's relation (`pivot.position`) is a `422` in `nested`: use `tags.pivot.position` from the owner. Includes, aggregates and actions accept relation groups. A `ScoutEngine` search with a filled text does not.
**Scope `parameters`** is a positional list: each value binds to the scope field at the same position, is validated by its rules, cast to its type and filled from its `default()`. A position is required unless its field has a default; a missing, extra or wrongly typed parameter is a `422` under `search.scopes.{index}.parameters`. A rule that names another field of the same scope, such as `gte:min`, compares with the parameter at that field's position. Scopes are prohibited next to a filled text on a `ScoutEngine` resource.
**`selects`** lists the selectable fields to return (`writeOnly()` and `selectable(false)` fields, and `virtual()` fields without `resolveUsing()`, are a `422` under `search.selects.{index}.field`); without it a row holds every selectable field of its resource. Include `selects` use the related resource's fields. A search loads only the columns its rows need: the returned fields, the key, their `requires()`, `alwaysSelect()` and the keys of the includes and of the relations the model loads itself (`$with`, a `with()` in a scope, a global scope or an instruction); `rest.search.select_columns` set to `false` loads every column.
**`sorts`** apply in order and accept the sortable fields of the resource (`Json`, `sortable(false)`, `writeOnly()` and `virtual()` fields without `sortUsing()` are a `422` under `search.sorts.{index}.field`). `direction` is `asc` or `desc` (`Lomkit\Rest\Fields\SortDirection`), `asc` when omitted. A sort also takes a field of a to-one relation (`author.last_name`: `BelongsTo`, `HasOne`, `HasOneThrough`, `MorphOne` and their of-many forms; never `MorphTo`, a to-many relation or a deeper path) that `max` accepts (sortable by its own column, not `virtual()`, `writeOnly()` or `Boolean`), or the key of an aggregate of the same level (`posts_count`). Both order by a subquery: the greatest related value within the related `perimeterQuery()`, or the aggregate computed again. They work at every level and within an include `limit`, and the relation needs `viewAny`. A missing value sorts as null, where the database puts it: first in ascending order on SQLite and MySQL, last on PostgreSQL. Without `sorts`, `defaultOrderBy()` applies, after the relevance order when the search has a text and its strategy orders by relevance. `@relevance` sorts by relevance (`desc` when `direction` is omitted) next to the other sorts, only with a filled text and a strategy that orders by relevance (`FullText` on MySQL, MariaDB or PostgreSQL), never in includes. With `ScoutEngine` and a filled text, sorts take its `sortable()` attributes, or `@relevance` alone for the engine order.
**Aggregate `type`** is required, one of `min`, `max`, `avg`, `sum`, `count`, `exists` (`Lomkit\Rest\Fields\AggregateFunction`). `field` is required for `min/max/avg/sum`, omit for `count/exists`. `min`/`max` take a field of the related resource that is sortable by its own column (`isSortableByColumn()`: sortable by default or `sortable()`, not `virtual()`, not `writeOnly()`; `sortUsing()` does not count, so a `Json` field with `sortUsing()` is refused) and is not a `Boolean` (an app type extending `Boolean` included), on every database; an `ID` with UUID keys (`HasUuids` or `keyType('uuid')`) works for `min`/`max` on every database and returns the same string (PostgreSQL reads it as text); `avg`/`sum` also need a `Number` field (`Number`, `Integer` or an app type extending `Number`). Another field is a `422` under `search.aggregates.{index}.field`. An optional `alias` renames the response key, which is `{relation}_{type}_{field}` otherwise, with the field name in lower case (`stars_max_rate`, `stars_count`). The alias looks like an identifier (`^[A-Za-z_][A-Za-z0-9_]*$`, at most 255 characters), and the key, alias or not, must differ from the fields and relations of the resource, from the other keys of includes and aggregates of the same search, from the gates key, from the type key inside a `MorphTo` include and from the pivot key inside a many-to-many include: a clash is a `422` under `search.aggregates.{index}.alias`, and under the `alias` of the include it clashes with. `relation` names a relation or a path of relations (`tickets.tags`, at most `rest.limits.depths.includes` relations): the aggregate counts each row at the end of the path once, applies the `perimeterQuery()` of every level and needs `viewAny` on each, its `filters` take the fields of the last level, and its key joins the relations (`tickets_tags_count`). A `MorphTo` relation, at the end of a path or inside it, is a `422`: aggregate a relation of a `MorphTo` target inside an include.
**`page`** is an integer of at least `1`; `limit` must be one of the resource's `limits()`; without it a page holds `$defaultLimit` rows.
**`includes`** accept `relation`, `alias`, `filters`, `sorts`, `scopes`, `instructions`, `aggregates`, `selects`, `gates`, `limit` and `includes`; `page`, `text` and `trashed` are a `422`. They nest up to `rest.limits.depths.includes` levels (default `3`), and each relation of a dotted include counts toward it (`author.posts` takes two levels). Includes and aggregates always run in SQL, so they are validated against the related resource's fields and operators, also next to `text`; a missing or undeclared `relation` is a `422`. A row returns only the relations the search includes: a relation that the model loads itself (`$with`, a hook) stays loaded for policies, hooks and `map()` but is not returned. A `MorphTo` include returns each target with its type first, under `@type` (`rest.morph.type_key`): the morph-map alias or the class name of its model. With several targets, set the search of each type under `types` (`{"relation": "labelable", "types": {"book": {"selects": [...]}}}`); without `types` every target loads and needs `viewAny`. A row whose stored type is not a target, or not a listed type, returns `null`, with no query. Filters, aggregates and nested includes apply on the target query, and it comes after the includes without an alias in the row. An include returns at most its `limit` rows for each parent row, or the `$includeLimit` of its resource (`10` by default) without `limit`, the first ones in its `sorts` or else in the resource's `defaultOrderBy()`; the relation a dotted include goes through (`posts` in `posts.comments`) is limited the same way, and applies its resource's `perimeterQuery()` like any include: a row it hides is not loaded, nor the rows below it, and an owner it hides is `null`. A `BelongsTo` or `MorphTo` include loads the one related row of each parent row, with no limit and no order: `limit` and `sorts` there are a `422` (`The search.includes.0.limit field is prohibited: this relation loads at most one record per parent.`).
**Include `alias`** renames the response key for that include, which lets the same relation appear several times under different constraints. It must look like an identifier (`^[A-Za-z_][A-Za-z0-9_]*$`), be unique across the includes, and not collide with a field, a relation, the key of an aggregate or of another include, the gates key of the resource, the type key inside a `MorphTo` include, or the pivot key inside a many-to-many include. An include without alias whose key is the name of a field needs an alias. It is used verbatim — no snake_casing — and is rejected on a dotted relation path (use a nested include there).
**`gates`** asks the server to evaluate the listed policy abilities per row and embed the result under the configured key (default `gates`). Each answer is `{"allowed": bool, "message": string|null}`, the message coming from a `Response::deny('...')` of the policy, and the `create` answer goes to `meta`. A row without answers (`"gates": []`, or `create` alone) returns `{}`. It takes `view`, `create`, `update`, `delete`, `restore`, `forceDelete` and the gates that the resource of the level declares in `gates()`, each once: `viewAny`, another name or a repeated one is a `422` under `search.gates.{index}`, and `create` is a `422` inside an include. `update`, `delete`, `restore` and `forceDelete` answer `{"allowed": false, "message": null}`, without calling the policy, for a row that their endpoint would refuse with a `422`: a search engine page row that `perimeterQuery()` leaves out (one query per page checks the page), or a trashed row for `update` and `delete` unless `perimeterQuery()` takes trashed rows in. `perimeterQuery()` runs with the request of the response: a condition that a gate must follow on one endpoint belongs in the policy, not in a `$this->request()->endpoint()` branch.
**`text`** runs the text search of the resource, see "Text search" below: `{"value": "desk lamp", "mode": "phrase"}`, `value` at most 255 characters. A blank, `null` or missing `text.value` runs the normal search and returns the normal list. A resource that declares no `text()` refuses `text` with a `422`. `viewAny` is checked before any search runs.
**`trashed`** (`with` adds the soft-deleted rows, `only` returns them alone) works with and without `text`, on a model that uses `SoftDeletes` only (a `422` otherwise), and not in includes. Next to a filled text on a `ScoutEngine` resource, it also needs `scout.soft_delete` set to `true`. `text.trashed` is a `422`.
**List limits** (`rest.limits`, at every level of the search): `sizes.filters` (default `100`) applies to `search.filters`, to each `nested` list and to the `filters` of includes and aggregates; `totals.filters` (default `500`) counts every filter of one search, at every depth and in its includes and aggregates; `sizes.values` (default `100`) applies to the values of `in`, `not in`, `between` and `not between`. `sorts`, `selects`, `scopes`, `aggregates`, `includes`, `instructions` and the `fields` of an instruction have their own limit in `sizes`; `gates` takes each ability once. Next to a search engine text, `sizes.sorts` also stays within what the engine runs. `totals.entries` (default `1000`) counts the entries of every list that takes a `rest.limits.sizes` value in one request, `gates`, `mutate` and the `fields` of actions included, except `resources`; the `parameters` of a scope keep their own limit, one value per field of the scope. A list over its limit is a `422` on its own key (`The search.filters field must not have more than 100 items.`), and nothing under it is validated or checked. A total gives `The search.filters.0.nested field goes over the limit of 500 filters in one search.` or `... goes over the limit of 1000 list entries in one request.` on the list that goes over it.

**Response** is Laravel's paginator JSON:

```json
{
  "current_page": 1,
  "data":        [{"id": 1, "name": "...", "gates": {"authorized_to_update": {"allowed": true, "message": null}}}],
  "from": 1, "to": 50, "per_page": 50, "last_page": 4, "total": 187,
  "meta":        {"gates": {"authorized_to_create": {"allowed": true, "message": null}}}
}
```

## Text search

Text search is opt-in per resource: `text()` returns a strategy. With `Contains` and `FullText`, `searchable()` marks the `Text` fields they read and the text is one more condition of the SQL search, so filters (with their whole grammar), scopes, instructions, includes, aggregates, sorts, pagination totals, `perimeterQuery()`, `viewAny` and actions work with it unchanged. `ScoutEngine` runs the text on a Laravel Scout search engine instead, see "Search engines" below.

```php
use Lomkit\Rest\Fields\{ID, Integer, Text};
use Lomkit\Rest\Text\{Contains, FullText, TextSearch};

public function fields(): array
{
    return [ID::make('id'), Text::make('name')->searchable(), Text::make('description')->searchable(), Integer::make('price')];
}

public function text(): ?TextSearch
{
    return FullText::make()
        ->index('name', 'description')   // one call per FULLTEXT index; without index(), one group of every searchable field
        ->language('english')            // PostgreSQL text search configuration (default english); MySQL ignores it
        ->otherwise(Contains::make());   // SQLite, SQL Server; without it, searching the resource there throws InvalidTextSearchException
}
```

- `Contains::make()` runs on every database: `ILIKE` on PostgreSQL, `LIKE` elsewhere. Each word must appear, as a part of the value, in one of the searchable fields, in any order; the words after the tenth are ignored; `%`, `_` and `\` match as typed. Modes `words` and `phrase`, no relevance.
- `FullText::make()` runs `MATCH ... AGAINST` on MySQL and MariaDB, and `to_tsvector` / `ts_rank` on PostgreSQL. All four modes, ordered by relevance. The `index()` columns must be searchable fields (`InvalidTextSearchException` otherwise). On MySQL and MariaDB each `index()` group needs a FULLTEXT index on exactly its columns, `$table->fullText(['name', 'description'])` in a migration; a missing one throws `InvalidTextSearchException`, which names the table and the group. On PostgreSQL the same migration, with `->language(...)` when the configuration is not `english`, creates the index that the search uses.
- `text.mode` is a case of `Lomkit\Rest\Text\TextMode`, `words` by default; a mode that the strategy does not support is a `422` under `search.text.mode`:
  - `words`: the words in any order. Contains and PostgreSQL need every word; MySQL matches any word and, without `sorts`, ranks the rows with more of them first.
  - `phrase`: the words next to each other, in this order.
  - `prefix`: each word starts a word of the row, for type-ahead (`lam` finds `lamp`).
  - `websearch`: words, `"quoted phrases"`, `-excluded` words, and `or` between two alternatives.
- FullText keeps the letters and digits of the text only: `+`, `*`, `:`, `&`, `|`, `!` and the other characters separate words and have no meaning, except the quotes, `-` and `or` of `websearch`. On MySQL and MariaDB an `_` between letters or digits stays in the word, as in the FULLTEXT index (`snake_case` is one word); PostgreSQL reads two words.
- With text, a strategy that orders by relevance and no `sorts`, the rows come most relevant first, then in `defaultOrderBy()`. `{"field": "@relevance"}` (`desc` when `direction` is omitted) places the relevance among the other sorts.
- A blank, `null` or missing `text.value`, or one without a letter or a digit, returns the normal list. A text made only of words that FullText ignores (stopwords, and on MySQL words under the minimum token size) returns no row. The text never narrows includes, aggregates or relation filters, and `text` in an include is a `422`.
- `search.trashed` (`with` / `only`) replaces `text.trashed`, with or without text.
- An action of a resource that declares `text()` accepts `search.text` and acts on the matching rows only.
- A model that uses `Laravel\Scout\Searchable` does not enable `text` by itself: its resource declares `text()` too.
- Database notes:
  - Contains: `ILIKE` ignores case on PostgreSQL; on MySQL `LIKE` follows the column collation (the default `_ci` collations ignore case); SQLite ignores the case of ASCII letters only.
  - MySQL and MariaDB FULLTEXT: no stemming (`lamps` does not find `lamp`). Words shorter than `innodb_ft_min_token_size` (3 by default) and the InnoDB stopwords (`the`, `with`, ...) are not indexed: `words` ignores them and `phrase` skips them. In `prefix` each word must start an indexed word of the row: `l` and `la` find `lamp`, but `the lam` misses `The lamp` because `the` is not indexed. In `websearch` such a word is required and makes the search return nothing; lower `innodb_ft_min_token_size` or change the stopword list, then rebuild the indexes. A `websearch` of excluded words only returns nothing. InnoDB only sees committed rows, so a test wrapped in a transaction (`RefreshDatabase`) finds none of the rows it wrote.
  - PostgreSQL: `english` stems words (`lamps` finds `lamp`) and drops English stopwords, `simple` only lowercases; the index must use the same configuration. A `null` value in one column of a group keeps the row from matching through that group: use `NOT NULL` columns, or a separate `index()` for a nullable column. A `websearch` of excluded words only returns the rows without these words.

## Search engines

`ScoutEngine` (Laravel Scout 11.9 or later) runs the text, the filters, the sorts, the pagination and the total on the search engine of a `Searchable` model, then loads the rows of the page in one SQL query by scout key, in the engine order. The index perimeter is required: `perimeter()` or `withoutPerimeter()`, never both.

```php
use Lomkit\Rest\Fields\{Integer, Number};
use Lomkit\Rest\Text\Engine\EngineQuery;
use Lomkit\Rest\Text\{ScoutEngine, TextSearch};

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

- `filterable()` takes fields (`ID`, `Integer`, `Number`, `Boolean`, `Text`, `Enum`, exactly these classes, without `filterUsing()` or `sortUsing()`) or the name of a field of the resource. Names match `^[A-Za-z_][A-Za-z0-9_]*$`, and `__soft_deleted` is reserved. `sortable()` names `filterable()` attributes. The second argument of `perimeter()` lists attributes that only the perimeter uses: clients never filter or sort on them. A declaration error throws `InvalidTextSearchException` naming the resource when the resource is first searched in a request.
- Index only attributes that every user who can list the resource may read: `search.text` matches what the index searches (every key of `toSearchableArray()` on the `database` and `collection` engines), and `searchable()` does not restrict it. Totals and pages follow the perimeter: use `withoutPerimeter()` only when every such user may see every indexed row. The perimeter alone limits a text search: declare there, on indexed filterable attributes, every condition that keeps rows from some users, and keep them in sync (`scout.after_commit` `true`, no `scout.queue` for such models).
- Grammar next to a filled text: filters on `filterable()` attributes, `and` only (`or`, `nested`, relation paths and relation groups are a `422`); `=`, `!=`, `in`, `not in` on every attribute and `>`, `>=`, `<`, `<=` on `Integer` and `Number`, within the field's operators; no `null`, non-empty lists, finite numbers. Sorts on `sortable()` attributes, or `@relevance` alone (`desc` or no direction) for the engine order. `text.mode` is `words` only. Scopes are a `422`. Instructions must implement `AppliesToEngine`. `trashed` needs `SoftDeletes` and `scout.soft_delete = true`. `includes`, `aggregates`, `selects` and `gates` run in SQL on the page rows. A blank text, or one without a letter or digit, runs the normal SQL search.
- The engine receives the letters and digits of the text, separated by spaces: the engine query syntax has no effect.
- The page is loaded by key without `perimeterQuery()`, with the model's global scopes and its soft deletes (`search.trashed`): pages are full and `total` exact, except for an indexed row that the database no longer holds or that a global scope drops (left out of `data`, still counted). Includes and aggregates of the page rows apply their own `perimeterQuery()`. Keys follow `getScoutKeyType()` (`int`/`integer` or string): a model whose scout key is a string column declares it. The model's `search()` override, `getScoutModelsByIds()` and `queryScoutModelsByIds()` are not used.
- Engines: `collection`, `database`, `meilisearch`, `typesense`, `algolia`, `turbopuffer`, `null`, and subclasses of them; another engine class is refused.
  - `collection`, `database`: every operator and sort, exact totals; the `database` engine reads only the scout key column of the rows that match, without the default eager loads of the model.
  - `meilisearch`: exact totals up to the index `pagination.maxTotalHits`; declare `filterableAttributes` (with the perimeter attributes) and `sortableAttributes` in the index settings.
  - `typesense`: `query_by` required in `scout.typesense.model-settings.<class>.search-parameters` (or `typesenseSearchParameters()`); pages of at most 250; at most 3 sorts.
  - `algolia`: no sorts (`sortable()` throws); `@relevance` alone is accepted.
  - `turbopuffer`: no sorts; pages within the first 10,000 results; the total counts the documents matching the filters, not the text.
  - `null`: always an empty page, without SQL.
  - Meilisearch and Typesense compare filter strings without regard to case: prefer `Integer`, `ID`, `Boolean` or `Enum` perimeter attributes.
- `EngineQuery` (perimeter and `handleEngine()`): `where($attribute, $operator, $value)` with `=`, `!=`, `<`, `>`, `<=`, `>=`; `whereIn()` and `whereNotIn()` with a list; `orderBy()` on a `sortable()` attribute; `options([...])` merged, never replaced. Only declared attributes and their operators; values are converted to the attribute type, and anything else throws `InvalidEngineQueryException` (a `LogicException`). Repeated `whereIn()` lists intersect, repeated `whereNotIn()` lists add up, and an empty `whereIn()` returns an empty page without calling the engine. There is no `query()` callback.
- Actions with a filled text read every key from the engine first (pages of `chunkCount`), then load each chunk in SQL by key, without `perimeterQuery()`, like a page, before running, queueing or batching. More matches than `maxResources` is a `422` under `search`; `search.limit` acts on the first `limit` results. Without `search.limit`, the action reads Meilisearch `pagination.maxTotalHits` or Algolia `paginationLimitedTo`: the application key must be allowed to read the index settings (a `422` otherwise), and a search that reaches a limit not above `maxResources`, or any limit without a cap, is a `422`, so set these limits above `maxResources`. Set `scout.after_commit` to `true`.

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
       "roles": [{"operation": "sync", "key": [1, 2, 3], "without_detaching": true}]
     }}
  ]
}
```

Per-item keys:

| Key | Type | Used by | Purpose |
| --- | --- | --- | --- |
| `operation` | string | all | `create`, `update`; in `relations` also `attach`, `detach`, and `sync`, `toggle` on many-to-many relations only |
| `attributes` | object | create / update / sync / toggle | Field values; only the fields the operation can write, validated by the field rules and the resource rules |
| `key` | int / string / array | update / attach / detach / sync / toggle | Target model id(s), typed like the resource's `ID` field; a list only in an entry of a to-many relation |
| `relations` | object | all | Map of `relationName → [operation objects]` (recursive) |
| `pivot` | object | entries of many-to-many relations only: `create`/`update`/`attach`/`sync`/`toggle` (refused on `detach`) | Pivot values; only the pivot fields the operation can write, validated by their rules |
| `without_detaching` | bool | `sync` | When `true`, behaves like `syncWithoutDetaching` |

An unknown key in an entry is a `422` on that key (`The mutate.0.atributes field is prohibited.`), and so are `pivot` outside an entry of a many-to-many relation and `type` outside an entry of a `MorphTo` relation. A `MorphTo` entry sends `type` (its morph type), required with several targets. `mutate`, to-many relations and `key` lists must be JSON lists (`The mutate field must be a list.`). Messages name `mutate` keys as written (`mutate.0.without_detaching`), unless a field's `label()` or the request's `attributes()` names them. `$request->payload()` returns the parsed entries as `Lomkit\Rest\Mutations\MutationNode` objects once the request passed validation, and the endpoint writes them.

Limits (`rest.limits`): `mutate` takes at most `sizes.mutate` entries (default `100`), each to-many relation at most `sizes.relation_entries` entries (default `100`), and a `key` list at most `sizes.values` keys (default `100`). `relations` nest up to `depths.mutations` levels (default `3`): a deeper `relations` is a `422`, `The mutate.0.relations.products.0.relations.category.relations.products.0.relations field is prohibited.`.

Writes go through the fields:

- `create` accepts the creatable fields, and `update`, `sync` and `toggle` accept the updatable fields (`Lomkit\Rest\Fields\MutationOperation::writeOperation()` maps the operation). Fields are creatable and updatable by default; `ID` and `readonly()` fields are not, so declare `->creatable()` on a key the client sends (a UUID). `writeOnly()` fields are accepted; a `virtual()` field only with `fillUsing(Closure(Model, mixed): void)`, which runs after the other fields of the entry. Another key is a `422` under `{entry}.attributes`: `The created_at key of the mutate.0.attributes field is not writable on create.` (`on update.` for `sync` and `toggle`), with or without Precognition.
- Each attribute is validated by its field: the type's base rule (`null` refused unless `nullable()`, arrays refused except on `Json`), `rules()`, and `creationRules()` or `updateRules()`. A rule naming another declared field (`required_if:featured,true`, `after_or_equal:published_on`) compares with that attribute in the same entry.
- A `Json` field works with or without a JSON cast on the model. When the model writes the attribute as it is (no cast, or a `string`, `encrypted` or `AsStringable` cast, and no set mutator), the field writes `json_encode()` of the value and the response returns the decoded column. Any other cast (`array`, `json`, `collection`, `AsArrayObject`, `AsCollection`, `AsFluent`, `AsVector`, a cast class of the app) or a set mutator (`setXAttribute()`, an `Attribute` with `set`) receives the value as sent and does both. A `fillUsing()` callback receives the value as sent. A `Json` pivot field does the same with the casts and mutators of the pivot class, so a `json` pivot column works on the default `Pivot`.
- In a nested write, the keys the relation sets win over the entry's attributes: the parent key and morph type (`getMorphClass()`, so morph map aliases) of `HasOne`, `HasOneOfMany`, `HasMany`, `MorphOne`, `MorphOneOfMany`, `MorphMany`, and the foreign key and morph type that `BelongsTo` and `MorphTo` associate. The parent relation's keys win over the entry's own `BelongsTo` or `MorphTo`, and the `BelongsTo` and `MorphTo` keys are read after the `mutating()` hook, so a key the hook associates is kept.
- Under `HasOne`, `HasOneOfMany`, `HasMany`, `MorphOne`, `MorphOneOfMany` and `MorphMany`, `update` and `detach` reach only rows whose foreign key (and morph type) names the parent; `attach` may move a row of another parent. `attach`/`create` under `HasOne` or `MorphOne` is a `422` on the relation entry while the parent has a related row (`The mutate.0.relations.loan field is prohibited: this relation already holds a record. Detach it first.`) unless an earlier entry detaches it; the of-many forms are exempt. Under `BelongsTo` and `MorphTo`, `detach` names the current owner (and `type`), never under a created row. Other keys are a `422` `The selected ... is invalid.`. The checks follow the entries in write order, during validation. A `MorphMany` detach clears the morph type.
- `sync` and `toggle` compare their keys with the attached rows that the related resource's `perimeterQuery()` returns (one query per parent row and relation); other attached rows stay. An `attach`/`detach`/`sync`/`toggle` target without attributes runs no resource hook and is saved only when its own keys change (a moved child, with its model events).
- `pivot` goes through the pivot fields of the relation (see Relationships): `create` and `attach` add a pivot row and accept the creatable pivot fields with `creationRules()`; `update`, `sync` and `toggle` accept the updatable pivot fields with `updateRules()` (`MutationOperation::pivotWriteOperation()` maps the operation); `detach` refuses `pivot`. `readonly()` pivot fields are refused, `writeOnly()` ones accepted. Each value is validated by its field (type base rule, `nullable`, `rules()`), with errors under `{entry}.pivot.{key}`, also when `pivot` is missing; another key is a `422` under `{entry}.pivot` (`The created_at key of the mutate.0.relations.tags.0.pivot field is not writable on create.`). A rule naming another pivot field compares with the pivot of the same entry, and a `Rule::when()` condition in a pivot field rule reads the pivot of its entry. Only the writable pivot keys reach the database. Limit: a row that `update`, `sync` or `toggle` adds gets the update rules, since validation runs before the database is read; use `attach` to add a row with the creation rules.
- A custom relation lists its operations as `MutationOperation` cases in `mutationOperations()`. The package writes each relation by the class of its Eloquent relation; a relation has no write hook: change a write in the resource's `mutating()` and `mutated()`.

**Response**: one entry per entry of `mutate`, at the same index — for the payload above, `{"operations": [{"operation": "create", "key": 72979, "relations": {"posts": [{"operation": "create", "key": 312}, {"operation": "attach", "key": 42}]}}, {"operation": "update", "key": 7, "relations": {"roles": [{"operation": "sync", "key": [1, 2, 3]}]}}]}`. Each entry has `operation`, `key` (the new key on `create`, the sent key or key list otherwise) and, when it sent `relations`, `relations` keyed as sent (an object for a to-one relation, a list for a to-many one); an entry of a `MorphTo` adds `type`, the morph type of its row. `sync` and `toggle` answer the keys they sent, not the rows they detach or attach besides; under a key list an entry runs once per key and answers once, a `create` with the list of its keys. To get the rows back, send `response`:

**Records**: `"response": {"selects": [{"field": "name"}], "includes": [{"relation": "posts"}], "gates": ["update"]}` next to `mutate` (it also takes `aggregates`) adds `records` after `operations`: one row per entry of `mutate`, at the same index, rendered like a search row (custom `map()` included), read after the transaction through the resource's `perimeterQuery()` — `null` when the row is out of it or gone. Nested entries get no record: reach their rows through `includes`, which keep their `limit` per record. Other search keys (`filters`, `sorts`, `limit`, `text`, ...) and the `create` gate are a `422` (`The response.filters field is prohibited.`). The block needs `viewAny` on the resource and on every resource its includes and aggregates reach, checked before any write (`403`, nothing written), and its gates answer for the rows as written.

`HasOneThrough` / `HasManyThrough` cannot be mutated — chain through the intermediate relation instead.

## Custom actions (`operate`)

Generate:

```bash
php artisan rest:action SendWelcomeNotificationAction   # → app/Rest/Actions/
```

```php
class SendWelcomeNotificationAction extends \Lomkit\Rest\Actions\Action
{
    // Optional. The rows the action runs on: ActionTarget::Search (default), ActionTarget::Resources
    // (the caller names the models by id) or ActionTarget::None (no model).
    // public \Lomkit\Rest\Actions\ActionTarget $target = \Lomkit\Rest\Actions\ActionTarget::Resources;

    // Optional. Cap on the rows the action runs on, for every target (default 1000): more is a 422.
    // null removes the cap. ->maxResources(5000) or ->maxResources(null) also sets it when declaring.
    // public ?int $maxResources = 1000;

    // Optional. Batch size when processing models (default 100).
    // public int $chunkCount = 100;

    // Optional. Hints for clients, given in details: ask before running, and the text to show.
    // public bool $destructive = true;
    // public ?string $confirm = 'Send the welcome notification?';

    // Optional. A policy ability checked on every row before the action runs (403 on a refused row).
    // public function authorizeEach(): ?string { return 'update'; }

    // Optional. Run every chunk in one database transaction (synchronous actions only).
    // public bool $atomic = true;

    // Optional. Route middleware of this action, after the resource and operate middleware.
    // public array $middleware = ['throttle:exports'];
    // public array $withoutMiddleware = ['auth:sanctum'];

    public function handle(\Lomkit\Rest\Actions\ActionFields $fields, \Illuminate\Support\Collection $models): void
    {
        // $fields->expires_at is a CarbonImmutable; $fields->notify is a bool, true when not sent
        foreach ($models as $model) { /* ... */ }
    }

    public function respond(\Lomkit\Rest\Actions\ActionFields $fields, int $impacted): ?\Lomkit\Rest\Actions\ActionResponse
    {
        return \Lomkit\Rest\Actions\ActionResponse::message("{$impacted} users notified"); // optional, null by default
    }

    public function fields(): array
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
public function actions(): array
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

Note the **`fields` payload is an array of `{name, value}` pairs**, not an object, and each name may appear only once (a repeated name is a `422` under `fields.{index}.name`). An entry accepts `name` and `value` only: another key is a `422` (`The fields.0.default field is prohibited.`). The list takes at most `rest.limits.sizes.fields` entries (default `100`), as do the `fields` of an instruction. The `search` block resolves the target models (the whole `search` schema without `page`, which is a `422`; `text` only on a resource that declares `text()`, and the action then acts on the matching rows only, while on other resources `text` is a `422` "The search.text field is prohibited."; with `ScoutEngine`, see "Search engines" for how the keys are read and capped); when sent, it must be an object. Actions with the `None` target omit `search` and receive an empty collection.

The target of an action (`Lomkit\Rest\Actions\ActionTarget`, the `$target` property or `->target()` when declaring) decides what the request body may carry:

| Target | `search` | `resources` | Models impacted |
|---|---|---|---|
| `ActionTarget::Search` (default) | optional | prohibited | whatever `search` resolves — **every model when `search` is omitted**, at most `$maxResources` |
| `ActionTarget::Resources` | prohibited | required, list of ids | exactly the ids named, each returned by the resource's `perimeterQuery()` |
| `ActionTarget::None` | prohibited | prohibited | none, `handle()` gets an empty collection |

Reach for `ActionTarget::Resources` when an action must never be able to hit every model by accident: the caller has
to name its targets, and omitting `resources` is a `422` rather than a silent mass update. Ids that do
not exist, or that `perimeterQuery()` hides, are a `422` under `resources.{index}`, checked with one query
per 1,000 ids. Keys are typed like the resource's `ID` field, and repeated ids are a `422`.
`resources` is capped at
`$maxResources` ids per request (default 1000) — lower it per action when the blast radius should be
smaller, raise it, or set it to `null` to remove the cap on every target.

```jsonc
// POST /api/users/actions/deactivate-users
{ "resources": [1, 5, 9], "fields": [{ "name": "reason", "value": "spam" }] }
```

`details` gives each action's target as `"target": "search"`, `"resources"` or `"none"`, and `destructive` and `confirm` (set with `->destructive()` and `->confirm('...')` when declaring, or as properties; `false` and `null` by default; hints for clients only).

**Fields are typed `Lomkit\Rest\Fields\*` objects.** Each field is validated with its type rules, `nullable()` and `rules()` as a normal Laravel validation: presence and cross-field rules (`required`, `required_if:status,published`, `present`, …) fire even when a field is absent from the `fields` array. Validation errors are keyed by field name — `fields.{name}` (e.g. `fields.expires_at`) — while an unknown or missing field name is reported positionally as `fields.{index}.name`. An `Enum` field must name its enum class (`Enum::make('status', PostStatus::class)`), because action fields are not bound to a model. Field names must not be numeric: `Integer::make('2')` throws `InvalidFieldException`. The request's `messages()` and `attributes()` apply to the `fields.{name}` keys, and so do translation lines keyed by the full key (`validation.custom.fields.{name}.{rule}`); `validation.attributes.{name}` still names the field, unless the field declares `label()`.

**Field rules run in the request's validator.** A rule that names another input compares with that input: the field parameters of Laravel's dependent rules (`required_if`, `gte`, `after`, `same`, …), `date_equals` and `in_array`, as strings or arrays, in `Rule::date()` / `Rule::numeric()` / `Rule::string()` / `Rule::array()` objects and inside `Rule::when()`. Other rule objects see the whole request: a `DataAwareRule` receives the whole payload, with each input value under `fields.{name}`, and rule objects and closures receive the full key (`fields.{name}`, `search.instructions.{index}.fields.{name}`) as their attribute.

**`handle()` receives `Lomkit\Rest\Actions\ActionFields`**: each declared input is a property cast to its field type (`Date`/`DateTime` → `CarbonImmutable`, `Integer` → `int`, `Boolean` → `bool`, `Enum` → the enum case), with `default()` values filled in; an input not sent and without default reads `null`. `has('name')` (sent, `null` included, or defaulted) and `filled('name')` (`has()` and not `null`, also `isset($fields->name)`) tell them apart; `only('a', 'b')` and `toArray()` return the inputs `has()` reports, in `fields()` order; `user()` is the user who sent the request (also in the worker) and `target()` the `ActionTarget`. A name that `fields()` does not declare throws `InvalidFieldException`. The object is read only and serializes (values, target, a reference to the user). An entry sent without `value` counts as absent.

**`$request->payload()` returns the parsed `Lomkit\Rest\Actions\OperationNode`** once the request passed validation, in the resource's `operating()` event for example (through `$this->request()`). The node has `action`, `fields` (the input values) and `target`: the parsed search of the `Search` target (a `SearchNode`), the keys of the `Resources` target (a `KeyList`), or `null` for the `None` target. `$request->payload()->keys()` returns the keys of the `Resources` target. The action's `fields()` runs once per request.

**Response**: `{"data": {"impacted": 150, "result": null}}`. `impacted` is the number of rows given to `handle()`, or put into jobs for a queued action, never the search `limit`. `result` is what `respond(ActionFields $fields, int $impacted): ?ActionResponse` returns, run once after the last chunk (or after the jobs or the batch are sent): `ActionResponse::message($text)` → `{"type": "message", "message": ...}`, `danger($text)` → `{"type": "danger", "message": ...}`, `download($url, $name)` → `{"type": "download", "url": ..., "name": ...}`, `redirect($url)` → `{"type": "redirect", "url": ...}`, `created($models)` → `{"type": "created", "keys": [...]}`; status 200.

**Chunks.** Rows reach `handle()` (or the jobs) in chunks of `$chunkCount`. A search without `sorts` and `limit` is read with `chunkById()` (ascending key), so an action that changes a filtered column still reaches every row; with `sorts` or `limit` the keys are read first in the search order and each chunk of keys is loaded through the same search; the `Resources` target loads `resources` in request order. Every target is capped by `$maxResources` (a count first, or at most `$maxResources` + 1 keys; `422` under `search`). Each synchronous chunk runs in a transaction on the resource model's connection; `->atomic()` (or `public bool $atomic = true`) runs all chunks in one (a queued atomic action throws `InvalidActionException`).

**Abilities.** Before validation, `operate` resolves the action: an unknown action is a `404` for a user allowed `viewAny` on the resource and a `403` for the others. A known action checks, in this order, `viewAny` on the resource, the abilities the resource's `abilities()` returns for `Endpoint::Operate` (none by default), then those `Action::abilities(): array` returns (`['viewAny']` by default, for every action, whatever its target). Both lists add to `viewAny`: an action returning `['export']` checks `viewAny`, then `export`. An action returning `[]` runs with none of these checks (no `viewAny`, no `Endpoint::Operate` abilities, and its search does not check `viewAny` on the resource): use it for an action any user may run, guests included. Includes, aggregates and filters on relations in the action's `search` still need `viewAny` on the related resource, as in a search.

**Routes and middleware.** `Rest::resource()` reads `actions()` when it registers the routes (at boot or during `route:cache`, without a user) and gives each action `POST /{resource}/actions/{uriKey}`, named `{name}.operate.{uriKey}`; `POST /{resource}/actions/{action}` comes last and answers unknown keys (`403` without `viewAny`, else `404`), also for an action that `actions()` returned only after the routes were registered. Keep `actions()` the same for every user and free of queries; run `route:cache` again after adding an action. `->middleware('throttle:exports')` and `->withoutMiddleware('auth:sanctum')` when declaring (or the `$middleware` and `$withoutMiddleware` properties) add to the package, resource and `middlewareFor('operate')` middleware and can remove any of them, group middleware included; name a removed middleware as declared, with its parameters. Two `throttle` middleware without a limiter name on one route count each request twice (they share the user's key): give the action a named limiter. Two actions with one `uriKey()` throw `InvalidResourceRouteException` when the routes register. The progress route of batches stays `GET /{resource}/actions/{action}/batches/{batch}`, without the action's middleware. `rest:check` reports an action route that removes the authentication middleware.

**Row checks.** `authorizeEach(): ?string` (null by default) names a policy ability checked on every row of the `Search` or `Resources` target, through the resource's `authorizedTo()` (memo, `authorizationCache()`: one store read and one write per chunk). By default all chunks are read and checked before any `handle()`, job or batch, then read again to run: a refused row is a `403` (status and message of the first refusal; `AuthorizationDenied` holds every refusal, at most 50, under `resources.{index}` or `search`). With `->skipUnauthorized()` (or `public bool $skipUnauthorized = true`) each chunk is filtered just before it is handed and `impacted` counts the allowed rows. The worker of a queued action does not check again. `skipUnauthorized()` without an ability, or an ability on the `None` target, throws `InvalidActionException` when the actions are read.

**Queuing**: implement `Illuminate\Contracts\Queue\ShouldQueue` to dispatch one job per chunk of `$chunkCount` models. Combine with `Lomkit\Rest\Contracts\BatchableAction` and use `withBatch(ActionFields $fields, PendingBatch $batch): void` to register `then`/`catch`/`finally` callbacks. Route the jobs with Laravel's queue attributes on the action (`#[Queue('exports')]`, `#[Connection('redis')]`, `#[Tries(3)]`, `#[Timeout]`, `#[Backoff]`, `#[Delay]`, `#[MaxExceptions]`, `#[FailOnTimeout]`) or `Queue::route(ExportOrders::class, 'exports')`; an action has no `$queue`/`$connection` properties and no `dispatch()`. The job (`Lomkit\Rest\Actions\QueuedAction`) holds the resource and action classes, the action key, the input values as sent, the row keys and a reference to the user; the worker rebuilds the context for that user (`$this->request()->user()`, policies and `perimeterQuery()` see them; `auth()->user()` does not), casts the input values again, and reloads the rows by key through `perimeterQuery()` (not for an engine text search) with the global scopes and `search.trashed`, without the search's includes. Rows gone or out of the perimeter are skipped; a missing user or a removed action fails the job with `QueuedActionException`. `release()`, `attempts()` and `batch()` work in `handle()`. A `BatchableAction` answers `"batch": "<id>"`; its jobs skip once the batch is cancelled (`SkipIfBatchCancelled`). `Rest::resource(...)->withBatchProgress()` adds `GET /{resource}/actions/{action}/batches/{batch}` (`{name}.batch`): the progress (`id`, `total_jobs`, `pending_jobs`, `failed_jobs`, `progress`, `finished`, `cancelled`) for the user who started the batch, `404` for anyone else and for a guest's batch.

## Metrics

A metric computes one result over the rows of a search: a count, a total, a breakdown. Declare it in `metrics()` on the resource (no request parameter; read it with `$this->request()`); each metric gets `POST /{resource}/metrics/{uriKey}`.

Generate one with `php artisan rest:metric OrdersByStatus --type=partition --group-by=status` (see "Artisan commands").

```php
use Illuminate\Database\Eloquent\Builder;
use Lomkit\Rest\Metrics\Metric;
use Lomkit\Rest\Metrics\MetricResult;

class TicketBacklog extends Metric
{
    public function type(): string
    {
        return 'gauge';                          // lowercase letters, digits and dashes
    }

    public function calculate(Builder $query): MetricResult
    {
        return new Gauge($query->where('status', 'open')->count(), 500);
    }
}
```

- `calculate(Builder $query)` receives an Eloquent query on the model: the perimeter of the resource, then the `search` of the request (text, trashed, filters, scopes, instructions), without order, selected columns or limit. Group, sum or count on it. It takes no request: read it with `$this->request()`.
- The result implements `Lomkit\Rest\Metrics\MetricResult` (`jsonSerialize(): array`). The response is `{"data": {"type": <type()>, ...<the result>}}`; a result with its own `type` key throws `InvalidMetricException`.
- Request: `{}` or `{"search": {...}}`. `search` accepts `text`, `trashed`, `filters`, `scopes` and `instructions`; `sorts`, `selects`, `includes`, `aggregates`, `gates`, `limit` and `page` are a `422` ("The search.limit field is prohibited."). `search.text` works with `Contains` and `FullText`; on a resource searched on an engine it is a `422`.
- The search runs the resource's `searching()` event first, as a search does. A metric that must skip it declares `TicketBacklog::make()->withoutSearching()` in `metrics()`, or `public bool $withoutSearching = true;` in its class. The perimeter, `viewAny` and the validation still apply.
- Abilities: `viewAny`, then the resource's `abilities(Endpoint::Metric)`, then the metric's `abilities()` (`['viewAny']` by default). `[]` runs without these checks, for example for guests with `->withoutMiddleware('auth')`. Relation filters still need `viewAny` on the related resource.
- Routes: named `{name}.metric.{uriKey}`; `POST /{resource}/metrics/{metric}` comes last and answers unknown keys (`403` without `viewAny`, else `404`). `->middleware('throttle:reports')` and `->withoutMiddleware('auth:sanctum')` (or the `$middleware` and `$withoutMiddleware` properties) work as for actions. `Rest::resource()` reads `metrics()` when it registers the routes: keep it the same for every user and free of queries; two metrics with one `uriKey()` throw.
- Details: `metrics` lists `{"name", "uriKey", "type", "ranges", "meta"}` for each metric. `$resource->definition()->metrics()` and `metric($key)` read them.

### Value, Partition and Progress

Three built-in types compute the usual results in one query. Extend one and call its helpers in `calculate()`; each helper returns the result to return.

```php
use Illuminate\Database\Eloquent\Builder;
use Lomkit\Rest\Metrics\Value;
use Lomkit\Rest\Metrics\ValueResult;

class TotalRevenue extends Value
{
    public function calculate(Builder $query): ValueResult
    {
        return $this->sum($query, 'price', 'placed_at');   // column, date column
    }

    public function ranges(): array
    {
        return [30 => '30 days', 90 => '90 days'];          // days; the first one is the default
    }
}
```

- `Value` (type `value`): `count($query, $dateColumn = 'created_at')`, `sum($query, $column, $dateColumn = 'created_at')`, `average()`, `min()` and `max()` with the parameters of `sum()`. With ranges, `{"value": <the last N days>, "previous": <the N days before>}`; without, `{"value": <every row>, "previous": null}` and the date column is not read. A count without ranges is the number of rows of the search.
- `Partition` (type `partition`): `count($query, $groupBy)`, `sum($query, $column, $groupBy)`, `average()`, `min()` and `max()` with the parameters of `sum()`. The group column is a `Text`, `Enum`, `Integer` or `Boolean` field. Each part is `{"key", "label", "value"}`: the key is the stored value (the enum value, an integer, a boolean), the label the enum case name or the key as text, the rows without a value form the part `{"key": null, "label": null, ...}`. Parts are sorted by value, largest first, then by key. A partition takes no `range`: filter the search instead.
- Limit: `OrdersByStatus::make()->limit(5)` in `metrics()`, or `public ?int $limit = 5;` in the class, keeps the 5 largest parts and folds the others into `other`: `{"label": "Other", "value": <their count, sum, min, max or average>}`. `other` is null when nothing is folded.
- Labels: `return $this->count($query, 'status')->labels(['paid' => 'Payée'], other: 'Autre');`.

```php
class OrdersByStatus extends Partition
{
    public function calculate(Builder $query): PartitionResult
    {
        return $this->count($query, 'status');
    }
}
```

`{}` -> `{"data": {"type": "partition", "parts": [{"key": "paid", "label": "Paid", "value": 5}, {"key": "pending", "label": "Pending", "value": 1}, {"key": null, "label": null, "value": 1}], "other": null}}`

- `Progress` (type `progress`): `count($query, $target, $dateColumn = 'created_at')`, `sum($query, $column, $target, $dateColumn = 'created_at')`, `average()`, `min()` and `max()` with the parameters of `sum()`. `{"value": <the last N days, or every row without ranges>, "target": <$target>}`: `$this->sum($query, 'price', target: 100000)` -> `{"data": {"type": "progress", "value": 52000.0, "target": 100000.0}}`. The target can come from the request: `target: $this->request()->user()->monthly_goal`.
- Ranges: `ranges()` maps a number of days to a label. The request sends `{"range": 90}` for another range; an undeclared key, or `range` for a metric without ranges, is a `422`. In a custom metric, `$this->range()` gives the selected `MetricRange` (`value`, `label`) or null. `ranges()` keys are integers greater than 0, or `Rest::resource()` throws.
- Numbers: a count is an integer; a sum, min or max is an integer for an `Integer` field and a float for another `Number` field; an average is a float. Over no row: count and sum `0`, average, min and max `null`. Floats keep their fraction in the response (`52.0`).
- Fields: the helpers read fields that the resource declares. A sum, average, min or max needs a `Number` field (`Integer` included), the range a `Date` or `DateTime` field; a partition groups by a `Text`, `Enum`, `Integer` or `Boolean` field; any other field throws `InvalidMetricException` when the metric runs.
- Response: `POST /api/orders/metrics/total-revenue` with `{}` -> `{"data": {"type": "value", "value": 52.0, "previous": 37.25}}`.

### Trend

A `Trend` gives one value per day, week or month over the range of the request.

```php
use Illuminate\Database\Eloquent\Builder;
use Lomkit\Rest\Metrics\Trend;
use Lomkit\Rest\Metrics\TrendResult;

class RevenuePerMonth extends Trend
{
    public function calculate(Builder $query): TrendResult
    {
        return $this->sumByMonths($query, 'price', 'placed_at');   // column, date column
    }

    public function ranges(): array
    {
        return [3 => '3 months', 6 => '6 months', 12 => '12 months'];   // months, this one included
    }
}
```

`{"range": 6}` -> `{"data": {"type": "trend", "series": [{"label": "2026-06", "value": 0.0}, ..., {"label": "2026-11", "value": 19.5}]}}`

- Helpers: `countByDays($query, $dateColumn = 'created_at', $splitBy = null)`, `countByWeeks()`, `countByMonths()`, and `sumBy...`, `averageBy...`, `minBy...`, `maxBy...` with `($query, $column, $dateColumn = 'created_at', $splitBy = null)`.
- Ranges: a Trend declares `ranges()`, or `Rest::resource()` throws. A key counts periods, the current one included: `6` on a monthly trend is this month and the 5 before. The series starts at the start of the first period and ends now.
- Labels: a day `2026-11-07`, a week the date of its first day (`2026-11-02` for a week from Monday), a month `2026-11`. Every period of the range is in the series, oldest first; a period without rows is `0` for a count or a sum, `null` for an average, a min or a max.
- Weeks start on Monday. `RevenuePerWeek::make()->weekStartsOn(Carbon::SUNDAY)` in `metrics()`, or `public int $weekStartsOn = Carbon::SUNDAY;` in the class, starts them on another day (`0` Sunday to `6` Saturday, or `Rest::resource()` throws).
- Time zone: `{"timezone": "Europe/Paris"}` counts the days, weeks and months in that zone, changes of offset included; without `timezone`, `config('app.timezone')`, the zone in which Laravel stores the dates. An unknown zone or an offset such as `+01:00` is a `422`. A `Date` field is a calendar date: only the range follows the zone. Every metric accepts `timezone`; a custom metric reads it with `$this->timezone()`, and `$this->periods()` uses it.
- Split: `$this->countByMonths($query, 'placed_at', splitBy: 'status')` adds `splits`, one series per value of the column, and `other`: `{"series": [<every row>], "splits": [{"key": "paid", "label": "Paid", "series": [...]}, ..., {"key": null, "label": null, "series": [...]}], "other": null}`. Keys and labels as a partition, sorted by key, the rows without a value last.
- Limit: `OrdersPerMonth::make()->limit(5)` in `metrics()`, or `public ?int $limit = 5;` in the class, keeps the 5 splits with the largest total over the range, chosen as the parts of a partition, and folds the others into `other`: `{"label": "Other", "series": [...]}`. `other` is null when nothing is folded. `->labels(['paid' => 'Payée'], other: 'Autre')` renames the splits and `other`.
- Fields: the date column is a `Date` or `DateTime` field, the column of a sum, average, min or max a `Number` field, the split column a `Text`, `Enum`, `Integer` or `Boolean` field; any other field throws `InvalidMetricException` when the metric runs.
- Databases: SQLite, MySQL, MariaDB and PostgreSQL.
- Periods in a custom metric: `$this->periods($query, 'placed_at', PeriodUnit::Day, 7)` gives the periods of the range (the count defaults to the range of the request; weeks follow `weekStartsOn`):

  ```php
  $periods = $this->periods($query, 'placed_at', PeriodUnit::Day, 7);
  $channels = $periods->group($query)
      ->selectRaw('count(distinct orders.channel) as channels')
      ->pluck('channels', 'period');

  return new TrendResult($periods->series($channels->all(), 0));
  ```

  `group()` selects `period` and groups by it inside the range; `series()` gives one point per period, the second argument for a period without rows; `labels()` lists the periods and `label()` is the SQL expression of a row's period.

### Table

A `Table` gives the top rows of a grouped aggregate: one row per value of a column, one column per count, sum, average, min or max, ranked by one of them.

```php
use Illuminate\Database\Eloquent\Builder;
use Lomkit\Rest\Metrics\Table;
use Lomkit\Rest\Metrics\TableColumn;
use Lomkit\Rest\Metrics\TableResult;

class TopCustomers extends Table
{
    public int $limit = 5;                                  // rows; 10 by default, 100 at most

    public function calculate(Builder $query): TableResult
    {
        return $this->rows($query, 'customer', [
            'orders' => TableColumn::count(),
            'revenue' => TableColumn::sum('price'),
            'basket' => TableColumn::average('price')->label('Average basket'),
        ], orderBy: 'revenue', dateColumn: 'placed_at');
    }

    public function ranges(): array
    {
        return [30 => '30 days', 365 => '365 days'];        // days, as a Value
    }
}
```

`{}` -> `{"data": {"type": "table", "columns": [{"name": "orders", "label": "Orders", "aggregate": "count"}, {"name": "revenue", "label": "Revenue", "aggregate": "sum"}, {"name": "basket", "label": "Average basket", "aggregate": "average"}], "rows": [{"key": "acme", "label": "acme", "values": {"orders": 2, "revenue": 150.0, "basket": 75.0}}, ...]}}`

- Helper: `rows($query, $groupBy, $columns, $orderBy = null, $direction = 'desc', $dateColumn = 'created_at')`. `$columns` maps a name (letters, digits and underscores, starting with a lowercase letter) to `TableColumn::count()`, `sum($field)`, `average($field)`, `min($field)` or `max($field)`; `->label('...')` names the column, else the name in words ("Revenue").
- Order: by `$orderBy`, the first column by default, largest first; `'asc'` for the smallest first. Rows without a value for that column come last; ties are ordered by key, the rows without a key last.
- Limit: `TopCustomers::make()->limit(5)` in `metrics()`, or `public int $limit = 5;` in the class. From 1 to 100, else `Rest::resource()` throws; 10 by default. The rows after the limit are not returned.
- Rows: `{"key", "label", "values"}`; keys and labels as a partition (the enum value and its case name; the rows without a value form the row `{"key": null, "label": null, ...}`). Numbers as a `Value`: a count is an integer, an average a float.
- Labels: `->labels(['paid' => 'Payée'])` renames rows. For labels stored elsewhere, read them for the keys of the rows:

  ```php
  $table = $this->rows($query, 'customer_id', ['revenue' => TableColumn::sum('price')]);

  return $table->labels(Customer::query()
      ->whereKey($table->keys())
      ->pluck('name', 'id')
      ->all());
  ```

- Ranges: as a `Value`, a key counts days and the table covers the last N days; without `ranges()`, every row.
- Fields: the group column is a `Text`, `Enum`, `Integer` or `Boolean` field, the column of a sum, average, min or max a `Number` field, the date column a `Date` or `DateTime` field; any other field throws `InvalidMetricException` when the metric runs. Index the group column of a large table: the database groups every row of the search before it keeps the top rows.

### Cache

A metric can keep its result between requests:

```php
public function metrics(): array
{
    return [
        TotalRevenue::make()->cacheFor(600),                          // seconds, a DateInterval or now()->addMinutes(10)
        OrdersByStatus::make()->cacheFor(600)->cacheScope('global'),  // one result per perimeter
    ];
}
```

- `->cacheFor()`, or `public ?int $cacheFor = 600;` in the class, keeps the result for that long; without it nothing is kept and no cache store is called. Less than 1 second throws `InvalidMetricException` when `Rest::resource()` reads the metrics.
- Each user gets their own result. `->cacheScope('global')`, or `public CacheScope $cacheScope = CacheScope::Global;` (`Lomkit\Rest\Metrics\CacheScope`), shares one result between the users and guests whose `perimeterQuery()` compiles to the same SQL and bindings: the users of one team share it, another team gets its own. Only that SQL is keyed: a perimeter that also depends on something else, such as a global scope of the model or one that it removes for some users, adds that to `authorizationCacheContext()`. Another scope than `'user'` or `'global'` throws `InvalidMetricException`.
- The key holds the user (guard, user class and key, or guest; with `'global'`, the SQL and bindings of `perimeterQuery()` instead), the resource, the metric class and `uriKey()`, the public properties of the metric that hold a number, a string, a boolean, `null` or an enum (`->limit()`, `->weekStartsOn()`, the scope, your own settings), the `range` and `timezone` of the request (the first range and `config('app.timezone')` when it sends none), its `search` (the keys of an object in any order), the values of the resource's `authorizationCacheContext()` and the stamps of the decision cache. A metric that reads anything else, such as a header, adds it to `authorizationCacheContext()`.
- The result goes to the store of `authorizations.cache.store` as the JSON text of `data`: any store works, `cache.serializable_classes = false` included.
- A kept result still runs validation, the abilities, the middleware and `searching()`; it skips every query of the metric. A metric with ranges counts them back from the instant of the first request until the result expires.
- `Rest::forgetAuthorizationsFor($user)` drops the results of that user (the global ones stay), `Rest::forgetAuthorizations()` drops every result, and `authorizations.cache.enabled = false` keeps none. After a deploy that changes what a metric computes, call `Rest::forgetAuthorizations()`.

## Instructions (custom search refinements)

Generate:

```bash
php artisan rest:instruction OddEvenIdInstruction   # → app/Rest/Instructions/
```

```php
class OddEvenIdInstruction extends \Lomkit\Rest\Instructions\Instruction
{
    public function handle(\Lomkit\Rest\Instructions\InstructionFields $fields, \Illuminate\Database\Eloquent\Builder $query): void
    {
        $query->whereRaw('MOD(id, 2) = ?', [$fields->type === 'odd' ? 1 : 0]);
    }

    public function fields(): array
    {
        return [\Lomkit\Rest\Fields\Text::make('type')->rules('required', 'in:odd,even')];
    }
}
```

To also run next to a text of a `ScoutEngine` resource, implement `Lomkit\Rest\Instructions\AppliesToEngine`; an instruction without it is a `422` there. An `AppliesToEngine` instruction that keeps the base `handle()` only applies to engine text searches and is a `422` in SQL searches (no text, blank text, includes, actions without text, `Contains`/`FullText` resources):

```php
class PricedUpTo extends \Lomkit\Rest\Instructions\Instruction implements \Lomkit\Rest\Instructions\AppliesToEngine
{
    public function handle(\Lomkit\Rest\Instructions\InstructionFields $fields, \Illuminate\Database\Eloquent\Builder $query): void
    {
        $query->where('price', '<=', $fields->max);
    }

    public function handleEngine(\Lomkit\Rest\Instructions\InstructionFields $fields, \Lomkit\Rest\Text\Engine\EngineQuery $query): void
    {
        $query->where('price', '<=', $fields->max);
    }

    public function fields(): array
    {
        return [\Lomkit\Rest\Fields\Number::make('max')->rules('required')];
    }
}
```

Register in `Resource::instructions()`. Clients invoke them via the `instructions` key inside `search`:

```json
"instructions": [{"name": "odd-even-id-instruction", "fields": [{"name": "type", "value": "odd"}]}]
```

`->meta([...])` (which merges) and `getMeta()` are also available on Instructions.

Instruction `fields()` are typed and validated exactly like Action fields: full Laravel validation (including `required` on absent fields), with errors keyed by name under `search.instructions.{index}.fields.{name}` (custom translation lines go under `validation.custom.search.instructions.*.fields.{name}.{rule}`); a name repeated in `fields` is a `422` under `search.instructions.{index}.fields.{position}.name`. `handle()` and `handleEngine()` receive `Lomkit\Rest\Instructions\InstructionFields`: the reading API of `ActionFields` (properties, `has()`, `filled()`, `only()`, `toArray()`), without `user()` and `target()`; both extend `Lomkit\Rest\Fields\FieldValues`.

## Relationships

All relations must extend `Lomkit\Rest\Relations\Relation` (do **not** use `Illuminate\Database\Eloquent\Relations\*`) and target a **Resource class**, not a model. Supported types: `BelongsTo`, `HasOne`, `HasOneOfMany`, `HasOneThrough`, `MorphOne`, `MorphOneOfMany`, `HasMany`, `HasManyThrough`, `MorphMany`, `BelongsToMany`, `MorphToMany`, `MorphedByMany`, `MorphTo`. A `MorphTo` takes one resource or a list: `MorphTo::make('labelable', [BookResource::class, ShelfResource::class])`.

```php
public function relations(): array
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

Many-to-many relations (`BelongsToMany`, `MorphToMany`, `MorphedByMany`) declare their pivot columns with typed `Field` objects in `pivotFields([...])` (`withPivotFields()` and `withPivotRules()` are gone; strings and duplicate names throw `InvalidFieldException`). Each pivot field is a column of the pivot table, written as sent (a `Json` pivot field encodes its value when the pivot class writes the column as it is, see `Json` above): a `virtual()` pivot field or one with `fillUsing()` or `resolveUsing()` throws `InvalidFieldException`. `getPivotFields()` returns a `FieldSet` bound to the pivot class of the Eloquent relation (the `using()` class or `Pivot`), so an `Enum` pivot field reads the pivot casts. The pivot fields decide what `pivot` accepts on each operation (see the `mutate` payload) and what the `pivot` of an included row returns: the selectable pivot fields only, never `writeOnly()` or `selectable(false)` ones. Filters on `{relation}.pivot.{key}` accept the filterable pivot fields only (not `writeOnly()` or `filterable(false)`), with the operators and values of their type. A custom relation with pivot fields implements `Lomkit\Rest\Contracts\PivotRelation` (the `HasPivotFields` trait does).

## Delete / restore / force

```
DELETE /api/users          body: {"resources": [5, 6]}      // destroy (soft if SoftDeletes is used)
POST   /api/users/restore  body: {"resources": [5, 6]}      // requires withSoftDeletes()
DELETE /api/users/force    body: {"resources": [5, 6]}      // requires withSoftDeletes()
```

All three return the affected records with the selectable fields of their resource: `{"data": [{...}], "meta": []}`. They take no `search`: an empty array is accepted, and any other `search` (`null` and blank strings included) is a `422` under `search`, so their rows carry no `selects`, gates or aggregates. Each entry of `resources` is a key typed like the resource's `ID` field (an integer for integer keys; a string, UUID or ULID otherwise), so a list or an object in `resources` is a `422` under `resources.{index}`. `resources` must be a list (an object is a `422`, `The resources field must be a list.`) of at most `rest.limits.sizes.resources` keys (default `1000`), and a repeated key is a `422` on every copy (UUID and ULID keys compare without case). During validation, the keys are checked through the resource's `perimeterQuery()`, with the global scopes (trashed rows included on restore and force), with one query per 1,000 keys: a key that it does not return, a trashed key on destroy included, is a `422`, `The selected resources.0 is invalid.` That query also loads the models, with the keys typed by the `ID` field whatever the model's `$keyType`, so `perimeterQuery()` runs once and the models are read before any event of the endpoint. Then every row is checked (`delete`, `restore` or `forceDelete`, through the resource's `authorizedTo()`) before any row is written: a denied row is a `403` whose `AuthorizationDenied` holds every denied row (at most 50, under `resources.{index}`), with the message of the first one in the order of `resources`. The rows are written in one database transaction on the connection of the resource's model, in the order of `resources`: the hooks and `perform*()` of each row run inside it, `destroyingAll()` and `destroyedAll()` (and their twins) inside it, before the first row and after the last, and a failure on any row rolls the others back.

## Authorization model

Two layers, configured in `config/rest.php`:

### 1. Policy authorization

Every model touched by a search/mutate/delete is checked against its Policy. Required policy methods:

| Policy method | Triggered by |
| --- | --- |
| `viewAny` | `search`, `details`, `operate` and `metric` (unless the action's or the metric's `abilities()` returns `[]`), and every resource reached by an include, an aggregate or a relation filter (each level, pivot columns included), a relation group or a relation sort, and the `response` block of `mutate` (its resource and every resource it reaches) |
| `view` | per-row visibility, includes |
| `create` | `mutate` create: once per created resource class with the model class, then per created row with its draft (`create($user, ?Model $draft = null)`) |
| `update` | `mutate` update, with a `\Lomkit\Rest\Authorization\Changes` third argument (`has()`, `get()`, `keys()`); a child moved under a has-one/has-many relation |
| `replicate` | `mutate` create when duplicating from existing data |
| `delete` | `destroy` |
| `restore` | `restore` |
| `forceDelete` | `forceDestroy` |
| the abilities of `->abilities(attach: 'attachTag', detach: 'detachTag')` on a relation, `($user, $parent, $related)` | links of that relation; without them, `update` on the parent |

**Define a policy for every model you expose.** A relation that needs its own link check declares its abilities; a declared ability with no policy method is denied. `php artisan rest:policy PostPolicy --model=Post` writes a starting point that allows every ability: edit it before production.

**Class-level checks run before validation and before any query.** The request checks its endpoint abilities (`viewAny` for `search` and `details`, none for `mutate`, `destroy`, `restore` and `forceDestroy`, for `operate` `viewAny` followed by the resource's `Endpoint::Operate` abilities and the action's `abilities()`, and the same for `metric` with `Endpoint::Metric` and the metric's `abilities()`), then `viewAny` on every resource the payload reaches and `create` for every created resource. A denied check answers `403` (or the status of the policy's response) even for an invalid payload or a Precognition request. The request's own `authorize()` runs first, and both must pass. Row checks of `mutate` run in one batch inside the transaction, before the first write; those of `destroy`, `restore` and `forceDestroy` before their transaction.

To change the endpoint abilities of one resource, override `abilities()` on it:

```php
public function abilities(\Lomkit\Rest\Http\Routing\Endpoint $endpoint): array
{
    return $endpoint === \Lomkit\Rest\Http\Routing\Endpoint::Destroy ? ['deleteAny'] : parent::abilities($endpoint);
}
```

The list replaces the endpoint's default: returning `['export']` for `Endpoint::Search` drops `viewAny` there. Keep `viewAny` in the list to add an ability (`['viewAny', 'export']`). `Endpoint::Operate` is the exception: its list adds to `viewAny` (see Custom actions).

These checks go through the resource's `authorizedTo()`, so an override of it applies to them. A denied check made before validation throws `\Lomkit\Rest\Exceptions\AuthorizationDenied` (an `AuthorizationException`) holding every denial of these checks (path such as `search.includes.0`, ability, resource class, key, message; at most 50). The body stays `{"message": ...}` with the message and status of the first denial, so `Response::denyAsNotFound()` still gives `404`. To add the denials to the JSON, register `$exceptions->render(new \Lomkit\Rest\Exceptions\DenialsRenderer);` in `bootstrap/app.php`'s `withExceptions()`; it leaves `404` responses unchanged. The row checks of `mutate`, `destroy`, `restore`, `forceDestroy` and of actions (`authorizeEach()`) throw `AuthorizationDenied` with every denial (`mutate.1.relations.tags.0`, `resources.{index}`, `search`).

The queries check `viewAny` again on each resource they search (and `create` on each resource they create), reusing the decisions made before validation. The endpoint's own resource is the exception: its endpoint abilities stand in for `viewAny`, so when they leave `viewAny` out (a resource override of `abilities()`, or an action whose `abilities()` returns `[]`) the queries do not check it there. With `authorizations.strict = true` (default `false`, meant for test suites: set `REST_AUTHORIZATIONS_STRICT=true` in `phpunit.xml`), a check on another resource that was not made before validation throws `UnplannedCheckException`.

Within a request, each distinct check (ability, resource, model or model class) runs the policy once and the later checks reuse its decision, so `GateEvaluated` listeners and `Gate::after()` see one evaluation per distinct check. A decision read from the cross-request cache dispatches no `GateEvaluated` either. Policies run with `Gate::forUser($request->user())`, and a change of user starts a new set of decisions. The package drops the decisions on a model after writing it, and keeps them for a row that a mutation only attaches, detaches or leaves unchanged; a hook that writes a model itself calls `$request->context()->authorizer()->forget($model)`.

The cross-request cache keeps only what a resource declares in `authorizationCache(): array` (ability => seconds, for example `['viewAny' => 300, 'view' => 60]`); a resource that declares nothing keeps nothing. Declared decisions are stored as scalars in the store of `authorizations.cache.store` (`null`: the default store), keyed by guard, user class and key, resource, ability, model class, key and `updated_at`, the values of `authorizationCacheContext(): array` (for example `['team' => $this->request()->user()?->current_team_id]`; integers, strings, booleans or `null`) and a stamp per user, and are read wherever the ability is checked, write endpoints included. Checks on unsaved models and the `update` checks of `mutate` are never kept. A row without `updated_at`, or written since the second its request started, keeps no decision; a search selects `updated_at` on a level that asks a kept row gate; a check on a row the request wrote is never kept. `Rest::forgetAuthorizationsFor($user)` drops a user's kept decisions and metric results (all guards, every store) and `Rest::forgetAuthorizations()` drops everyone's, guests included. `authorizations.cache.enabled = false` (`REST_AUTHORIZATIONS_CACHE_ENABLED=false`) turns it off for every resource.

### 2. Gates

When the client passes `"gates": [...]` in `search`, the listed abilities are evaluated per row and attached under the configured `gates` key. The keys returned (`authorized_to_view`, `authorized_to_create`, etc.) are configurable under `rest.gates.names`. A field named like the gates key throws `Lomkit\Rest\Exceptions\InvalidResourceException` when the fields of its resource are first read.

Each answer is `{allowed, message}`, where `message` comes from a `Response::deny('...')` returned from the policy method, or `null`; in a custom response, `map()` reads the answers of a row as an object. To turn the gates of a resource off, override `isGatingEnabled()` and return `false`, or do it in the base resource `app/Rest/Resources/Resource.php` for every resource; there is no config switch.

A resource declares more gates, as policy methods that receive the user and the model:

```php
public function gates(): array
{
    return ['download'];
}
```

Clients list `download` in `gates` next to the built-in abilities, at every level of that resource, and each row answers `authorized_to_download` (renamed in `rest.gates.names` like the others) from `download($user, $model)` on the policy. A declared gate is row-level only, never in `meta`, and a policy without the method denies it. A name is camelCase, listed once, neither `viewAny` nor a built-in ability: another value throws `InvalidResourceException` when the gates are first read (details, or a search with `gates`). The details list every accepted gate under `gates`.

## Custom Response

`php artisan rest:response UserResponse` scaffolds a class with a `map($model, $responseModel)` method that returns the array shape sent to the client. `map()` is the only method to override: it receives each row of the page or the list, and each record of a mutation's `response`, as the package rendered it (`selects`, `gates`, `aggregates`, `includes`), and the package builds the rest of the response. `map()` reads the columns that the search loaded: list in `alwaysSelect()` a column that it reads and the row does not return. Assign on the Resource:

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

Docs are served at `/docs/api`. Fields, validation rules, relations, search filters, and mutate payloads are inferred automatically from the resources. The search `filters` list the filterable fields (dotted paths are not listed) and every `FilterOperator`. The search `text` (with the `mode`s of its strategy), `trashed` (on `SoftDeletes` models) and the `@relevance` sort only appear on the resources that support them. The mutate `attributes` list the writable fields only, with their field and resource rules for `create` and `update`. Relations come from the relation objects of `relations()` (their class name, `mutationOperations()` and number of entries), and the `pivot` of a many-to-many entry lists the writable pivot fields with their rules for `create` and `attach`, then `update`, `sync` and `toggle`. The details response is documented with its entries: the field entries, the relations with `pivot_fields`, `types` and `morph`, the rules, `text`, `gates`, and the input entries of actions, instructions and scopes.

## Precognition

Set `rest.precognition.enabled = true` (default `false`). Clients send `Precognition: true` (and optionally `Precognition-Validate-Only`) on any endpoint, and the request is authorized (the class-level checks above, a denied one answers `403`), validated and short-circuited before the controller body runs — `204 No Content` on success, `422` on validation failure. Useful for live frontend form validation. A name in `Precognition-Validate-Only` also covers the keys under it: `search` validates `search.limit`, `search.filters.0.field` and every other key of the search, while `search.filters` leaves `search.limit` out.

## Artisan commands shipped by the package

- `rest:resource {Name} --model=Model` — generate a Resource. Without `--model`, the model is guessed from the name (`PostResource` → `App\Models\Post`) when that class exists; otherwise the `$model` line is left out and the command warns.
- `rest:resource {Name} --model=Model --generate` (`-G`) — read the model's table and declare one field per column: the key as `ID::make(<key name>)`, the type from the cast or the column type (`Enum` for a backed enum cast, `Json` for JSON casts, `AsFluent`, `AsVector` and `json` or `jsonb` columns (on SQLite, a `json` column is text unless the connection sets `use_native_json`, and a `jsonb` column unless it sets `use_native_jsonb`, so `Text` without a JSON cast), unknown types as `Text` with a note), `nullable()` for nullable columns, `readonly()` for timestamps and auto-increment or generated columns, `writeOnly()` for `$hidden` columns (a hidden column the model does not let fill is left out with a note), `rules('max:N')` when MySQL or PostgreSQL report a string length, and `creationRules('required')` plus `Rule::unique(Model::class, 'column')` for required and uniquely indexed columns. It also declares the relations: each public model method with a declared Eloquent relation return type (`BelongsTo`, `HasOne`, `HasMany`, `BelongsToMany`, `MorphOne`, `MorphMany`, `MorphToMany`, `morphedByMany()` as `MorphedByMany`, `HasOneThrough`, `HasManyThrough`) whose related model has a resource under `app/Rest/Resources` (or is the model itself), e.g. `BelongsTo::make('author', UserResource::class)->requiredOnCreation()` with `author_id` made `readonly()`, and `pivotFields([...])` typed from the pivot table for `withPivot()` columns and `withTimestamps()` (pivot timestamps `readonly()`; a declared column missing from the pivot table is left out with a note). Relations to a model without a resource, and `MorphTo`, are skipped; the `MorphTo` note prints `MorphTo::make('<name>', [...])` with the resources of the morph map. It prints `PostResource ... 8 fields, 1 relation`, one `Skipped:` line per relation not generated (with the `rest:resource` command to run first for a missing resource) and one `Note:` line per column to review. It exits 1 before writing anything when the model is missing, is not a model or cannot be instantiated (an abstract model), or its table is missing; rerun with `--generate --force` to regenerate an existing resource. Interactively, without the flag, it asks `Generate the fields and relations from the <table> table?` (default yes) when the table exists; with `-n` and no flag the resource declares only the key. Review the generated fields before exposing the resource.
- `rest:controller {Name} --resource=ResourceClass` — generate a Controller. Without `--resource`, the resource is guessed the same way (`PostsController` → `PostResource`); otherwise the `$resource` line is left out and the command warns. Set `$resource` before you register the controller with `Rest::resource()`: a controller without it stops the route files and every Artisan command, the generators included.
- `rest:base-resource` / `rest:base-controller` — generate the empty abstract base classes `app/Rest/Resources/Resource.php` and `app/Rest/Controllers/Controller.php` that all your resources/controllers should extend. `rest:resource` and `rest:controller` create them when they are missing. Override the `*Query` hooks in the base resource to constrain every resource.
- `rest:action {Name}` — scaffold an Action.
- `rest:instruction {Name}` — scaffold an Instruction.
- `rest:metric {Name} --type=value|trend|partition|progress|table|custom --group-by=field` — scaffold a metric in `app/Rest/Metrics`: `value` (`$this->count($query)`), `trend` (`$this->countByDays($query)` by `created_at`, ranges 7, 30 and 90 days), `partition` (`$this->count($query, '<field>')`), `progress` (`$this->count($query, target: 100)`), `table` (`$this->rows($query, '<field>', ['count' => TableColumn::count()])`), or `custom` (extends `Metric`, `type()` is the class name in kebab case, `calculate()` returns a `ValueResult` to replace with your own result). `--group-by` is required for `partition` and `table` and ignored, with a warning, by the others. Interactively it asks for the type, then for the field; with `-n` and no `--type` it writes a `value` metric. Declare the class in `metrics()`.
- `rest:response {Name}` — scaffold a custom Response class.
- `rest:policy {Name} --model=Model` — generate a policy that allows every ability in `app/Policies`: `viewAny`, `view`, `create`, `update` and `delete` (plus `restore` and `forceDelete` with `SoftDeletes`) return `true`. Edit it before production. The user parameter is typed with the model of the default guard's user provider. Without `--model`, the model is guessed from the name (`PostPolicy` → `App\Models\Post`). A model that Gate already maps to another policy keeps it: nothing is written, and with `--force` the file is written but Gate still uses the other policy until you register the new one with `Gate::policy()`.
- `rest:quick-start --model=Post --model=Tag` — for each model, write the resource from its table (as `--generate`), the controller and, when Gate finds no policy, a policy that allows every ability, then append `Rest::resource('posts', PostsController::class)->middleware('auth:sanctum')` to `routes/api.php` once per controller (`auth:<guard>` with `--guard=<guard>`). Related models that are not chosen are skipped. Interactively it asks for the models, and for the guard when an install is needed and `--guard` is not passed, `--install-api` included; with `-n`, `--model` is required, the guard is `sanctum` unless `--guard` is passed, and `--install-api` is required when `routes/api.php` is missing, or when the run adds routes and the package of the guard (Laravel Sanctum or Laravel Passport) is missing. It ends with a check in a fresh process (routes authenticated, a policy found per model) and exits 1 when blocked. Existing files are reported as `kept`, so a rerun changes nothing.
- The generators keep an existing class unless `--force` (`-f`) is passed, and read the published `stubs/rest.<name>.stub` before the package stubs. Stubs published under 2.x generate untyped properties, which are a fatal error in 3.x: delete them, or publish them again with `php artisan stub:publish --force`.

## Working conventions and gotchas

- **Don't write custom controller methods.** If you find yourself wanting to, the right tool is almost always an `Action` (mutation) or an `Instruction` (query). Falling back to a plain Laravel controller bypasses the package's authorization layer.
- **Whitelist by declaration.** Adding a column to the table doesn't expose it — it must be in `fields()`. Same for scopes, relations, actions, instructions.
- **A search loads only the columns it returns.** A policy, accessor, observer, hook or `map()` that reads another column gets `null` (a `MissingAttributeException` with `Model::preventAccessingMissingAttributes()`): list the column in `alwaysSelect()` or in the field's `requires()`, or set `rest.search.select_columns` to `false`. Actions, `destroy`, `restore`, `forceDestroy` and mutation lookups still load every column. A `select()` in a scope or an instruction is kept and the search adds its columns, but a `<table>.*` in it (also the one of `$withCount`) is replaced by them; a `select()` in a global scope runs after the search and replaces them.
- **Relation classes are the package's, not Eloquent's.** Use `Lomkit\Rest\Relations\HasMany` (not `Illuminate\Database\Eloquent\Relations\HasMany`), pass `::make('name', TargetResource::class)`, and target the **Resource**, not the model.
- **Soft deletes are opt-in at the route.** Even if the model uses `SoftDeletes`, `restore` / `force` routes only register when `->withSoftDeletes()` is chained onto `Rest::resource(...)`.
- **Don't bypass policies by overriding `isAuthorizingEnabled()`** — write the policy. The package expects every policy to run, including `viewAny`, `replicate`, and the link abilities that relations declare.
- **`mutate` is one batch but per-item-authorized.** A single denied item fails the whole batch.
- **`includes` nest up to `rest.limits.depths.includes` levels** (default `3`) — each include accepts `relation`/`alias`/`filters`/`sorts`/`scopes`/`instructions`/`aggregates`/`selects`/`gates`/`limit` and its own `includes`; `page`, `text` and `trashed` are a `422`, and so are `limit` and `sorts` on a `BelongsTo` or `MorphTo` include. Without `limit`, an include returns at most `$includeLimit` rows (`10`) per parent row. Each relation of a dotted include counts as one level. A deeper `includes` is a `422`, `The search.includes.0.includes.0.includes.0.includes field is prohibited.`, and a dotted include that goes deeper gives `The search.includes.0.relation field goes deeper than the limit of 3 include levels.`; raise the config value to go deeper.
- **`=` or `!=` with `null` is a `422`.** Filter the rows without a value with `{"field": "summary", "operator": "is null"}`, on a `nullable()` field only.
- **Action `fields` payload is an array of `{name, value}` pairs**, not an object — easy mistake: an object keyed by name is a `422`, `The fields field must be a list.` The same goes for instruction `fields`.
- **One validator per request.** A package request turns its payload into rules on explicit keys, then validates them in one pass, so `withValidator()` and `after()` on a request subclass run once and see every error. Every payload is parsed into rules on explicit keys (`search.filters.0.field`, `fields.0.name`, `mutate.0.attributes.name`), and `SearchRequest::payload()`, `OperateRequest::payload()` and `MutateRequest::payload()` return it as typed nodes (`Lomkit\Rest\Search\SearchNode`, `Lomkit\Rest\Actions\OperationNode`, a list of `Lomkit\Rest\Mutations\MutationNode`) once the request passed validation. An override of `rules()` or `passedValidation()` must call the parent, and a rule that it adds for a parsed key after `...parent::rules()` adds to the package rules of that key and runs after them. Laravel expands the wildcard rules of `rules()` (`mutate.*.attributes.name`) and of the resource rules, wherever the override writes them. A list over its `rest.limits.sizes` value gets one `422` on its own key, and the package reads, checks and validates nothing under it; a wildcard rule of `rules()` with more than one `*` (`mutate.*.relations.products.*.attributes.name`) still runs on the entries of a nested list over its limit. The package has no rule nodes any more: the `rules()` of a request hold Laravel rules only. The `label()` and `messages()` of fields are added under the request's `attributes()` and `messages()`, which still come first for a key or a key pattern. To adjust the validator, use `withValidator()` or override `createDefaultValidator()` and call the parent: a request that defines `validator()` must build it from `validationData()` and `validationRules()`, and loses the labels, messages and names that the package gives to keys. Overrides of `validationData()`, `validationRules()`, `createDefaultValidator()` and `shouldValidatePrecognitiveAttribute()` must keep the parent's return types.
- **Scope `parameters` are positional.** They bind to the scope's fields in declared order, so reordering a scope's fields changes what clients must send. Only model local scopes run: `withTrashed`, `latest` and other Builder methods or macros cannot be called as scopes.
- **`HasOneThrough` / `HasManyThrough` can be searched/included but not mutated.**
- **Cache invalidation.** A declared decision on a row follows the row's `updated_at` (Eloquent writes only, second precision). A change to a user's roles or to what a `Gate::before()` callback reads needs `Rest::forgetAuthorizationsFor($user)`; a policy change needs `Rest::forgetAuthorizations()` or the seconds of the ability. Set `REST_AUTHORIZATIONS_CACHE_ENABLED=false` while debugging policies, or return `[]` from the `authorizationCache()` of a resource.
- **`config/rest.php` values are typed.** The switches take `true`, `false`, `"1"`, `"0"`, `"true"` or `"false"` (so `env()` values work), `gates.key` and `morph.type_key` a non-empty string, `gates.names` a map of strings, `authorizations.cache.store` `null` or a non-empty string, and every `limits` value an integer of 0 or more. A value of another type and an unknown key under `rest.limits` (such as `sizes.filter`) throw `Lomkit\Rest\Exceptions\InvalidConfigException`, which names the key. The 2.x keys `gates.enabled`, `gates.message`, `authorizations.enabled`, `search.max_nesting_depth`, `authorizations.cache.default` and `documentation` are removed: override `isGatingEnabled()` or `isAuthorizingEnabled()` and return `false` instead of the switches, `limits.depths.filters` instead of `search.max_nesting_depth`, and `authorizationCache()` instead of `authorizations.cache.default`.

## Pointers into the codebase

- Service provider & routing — `src/RestServiceProvider.php`, `src/Rest.php`, `src/Http/Routing/` (`Endpoint`, `PendingResourceRegistration`, `EndpointRoutes` (the routes of each endpoint, one per action for `operate` and one per metric for `metric`), `ResourceRegistrar` and the route value objects)
- Metrics — `src/Metrics/` (`Metric`, `MetricResult`, `HasMetrics`, `MetricQuery`, `MetricNode`; the built-in types `Value`, `Partition`, `Progress` and their results; `MetricRange`, `MetricColumn` (typed checks), `Aggregate` and `AggregateQuery` (the SQL), `HasLimit`; `Trend`, `TrendResult`, `Periods` and `PeriodUnit`, and `Grouping/` (the SQL of a period per database); `Table`, `TableColumn` and `TableResult`; `HasCache`, `CacheScope`, `MetricKey` and `CacheMetricStore` (the result cache)), `src/Http/Requests/MetricRequest.php`, `src/Http/MetricResponse.php`
- Config — `src/Config/RestConfig.php` (the typed values of `config/rest.php`, read once per request through `RestContext::config()`) and `src/Config/ConfigValue.php` (the typed read of one key, also used for `rest.limits` and `rest.precognition.enabled`)
- Request classes per verb — `src/Http/Requests/{Search,Mutate,Operate,Destroy,Restore,ForceDestroy,Details,Batch}Request.php`
- Resource base class & traits — `src/Http/Resource.php`, `src/Http/ResourceDefinition.php` (the declarations of one resource for one request, read with `$resource->definition()`, and their lookups `relation()`, `scope()`, `action()`, `instruction()` and `inputFields()`; kept by `src/Http/RestContext.php`, whose limits are a `src/Http/ResourceLimits.php`), `src/Concerns/Resource/*` (`DeclaresFields`, `DeclaresScopes`, `Paginable`, `Relationable`, `Contextual` with `definition()` and `request()`, hooks, ...)
- Authorization — `src/Authorization/*` (`Authorizer`, `Check`, `Changes`, `Decision`, `Denial`, `DecisionMemo` and the decision stores), `src/Concerns/Authorizable.php`, `src/Exceptions/{AuthorizationDenied,DenialsRenderer,UnplannedCheckException}.php`; the checks made before validation are collected by the parsers (`ParsedRules::reach()`), and run by `RestRequest::passesAuthorization()`
- Query layer — `src/Query/PageQuery.php` (the default `paginate()`: the page first, the count only when the page does not give the total), `src/Query/EngineSearch.php` (the search of a `ScoutEngine` text), `src/Query/AttributeWriter.php`, which fills a model from the writable fields of a mutation, `src/Query/RelationAggregate.php` (the subquery of an aggregate over a relation, selected by `AggregateClause` and ordered by `RelationSortClause` and `AggregateSortClause`), and `src/Search/Plan/*` (`SearchPlanner`, `QueryPlan` (its `query()` starts from the query of the model and applies the plan), `PlannedPage` (the plan and the rows of a search engine page), `LimitPolicy`, the row sources `SqlRows` and `KeyedRows` (the rows of a `KeySet`: the `EngineKeys` of an engine page, or the `RecordKeys` of the records of a mutation), the column selection in `src/Search/Plan/Columns/` (`ColumnSet`, the columns of one level; `ColumnSelection`, the returned columns or every column; `RelationKeys`, the keys of each Eloquent relation class), and the `IncludeClause`, `AliasedIncludeClause` (an alias loaded on copies of the parent rows), `MorphToIncludeClause` (a `MorphTo` include, loaded per stored type) and `DefaultOrderClause` clauses)
- Responses — `src/Http/Response.php` (the envelope, `map()` and `rows()`), `src/Http/MutateResponse.php` (the mutation in one transaction, its `operations` and, with `response`, its `records`, read by `src/Http/WrittenRecords.php`), `src/Http/OperateResponse.php` (`impacted` and the `result` of an action), `src/Http/BatchProgress.php` (the progress of a batch) and `src/Http/Serialization/*`: `RowSerializer` renders the rows of one plan level with `RowFields` (which converts only the returned columns), `GateTable`, `RelationRenderer` and `PivotRenderer`, and `MorphToSerializer` the targets of a `MorphTo` include, type first (both are `RowRenderer`s), and `PageEnvelope` or `PlainEnvelope` (the `Envelope` strategies) wraps them; `src/Pagination/LengthAwarePaginator.php` gives the page keys
- Writes — `src/Mutations/Mutator.php` (writes the entries of `payload()` in the transaction of `MutateResponse`, reads each row from the `RowSet` that the key check of validation loaded (`src/Mutations/RowSet.php`, one instance per row) and collects the entries of the response in an `OperationLog` of `OperationEntry` objects) and `src/Mutations/RelationWrites.php` (the writes of each Eloquent relation class: `OwnerWrites`, `ChildWrites`, `PivotWrites` with `PivotSet`, `NoWrites`), checked before the first write by `src/Mutations/WriteChecks.php`
- Text search — `src/Text/*` (`TextSearch`; the SQL strategies `Contains` and `FullText` implement `QueryTextSearch`, with the database dialects in `src/Text/Dialects/`; `ScoutEngine` implements `EngineTextSearch`; `ResolvedText`, the text search of a resource for one request, read through `$request->context()->text($resource)`; `TextQuery`, `TextMode`, `TextCapabilities`), applied by `TextClause`, `RelevanceClause` and `TrashedClause` in `src/Query/Clauses/`
- Search engines — `src/Text/Engine/*` (`EngineQuery`, `IndexAttributes`, `EnginePage`, `ScoutModel`) and one profile per Scout engine in `src/Text/Engine/Profiles/`; engine filters and sorts are validated by `src/Text/Engine/{EngineSearchGrammar,EngineFilterParser}.php`, and actions read their keys through `src/Actions/{EngineTargets,EngineTargetKeys}.php`; `EngineCriteria` and `EngineInstruction` build the engine query from the nodes of `payload()`
- Actions / Instructions / Scopes — `src/Actions/{Action,ActionTarget,ActionFields,ActionResponse,RowChecks,Actionable}.php` (`RowChecks`: the `authorizeEach()` checks of an action), `src/Actions/Rows/*` (`ChunkedRows`, `KeyChunks`, `NoRows`: the rows of each target; `RowCap`: `$maxResources`), `src/Actions/BatchOwner.php`, `src/Instructions/{Instruction,InstructionFields,Instructionable,AppliesToEngine}.php`, `src/Scopes/Scope.php`
- Typed fields and input values — `src/Fields/*` (`Field`, `Concerns/ConfiguresMessages`, `FieldSet`, `FieldPath`, `InputValues`, `CastValues`, `FieldValues`, `MutationOperation`, `WriteRules`, `Details/FieldDetails`, `Details/ResourceRuleDetails`, `Details/InputFieldDetails`), filter operators and conditions in `src/Fields/Filtering/`, clauses in `src/Query/Clauses/`, built from the nodes of `payload()` with `of()` (filters: `FilterGroupClause`, `FieldConditionClause`, `RelationConditionClause`, `RelationGroupClause`)
- Relations — `src/Relations/*.php` (the relation classes follow the Eloquent hierarchy: `MorphTo` extends `BelongsTo`, `MorphToMany` and `MorphedByMany` extend `BelongsToMany`, `HasOne`, `HasMany`, `MorphOne` and `MorphMany` extend `HasOneOrMany`, the through relations extend `HasOneOrManyThrough`) (`MorphColumns`, the columns of a `MorphTo` in the details); `src/Contracts/PolymorphicRelation.php` and `src/Relations/MorphTargets.php` (the targets of a `MorphTo`, by morph type); `src/Contracts/OwnerRelation.php` (`BelongsTo` and `MorphTo`: one row per parent, no include limit); pivot fields in `src/Concerns/Relations/HasPivotFields.php` (`src/Contracts/PivotRelation.php`), validated by `src/Validation/Mutate/EntryRules.php`
- Validation of the request bodies — the parsers in `src/Validation/{Search,Operate,Mutate}/*` (the search parser with `SearchGrammar`, `SqlSearchGrammar` (with `SortPaths`, the sort names of a level and its relation paths) and `LevelBounds`, the bounds of a level, and `RowKeys`, the keys of a row, read by the `IncludeAlias` and `AggregateAlias` rules), the typed nodes in `src/Search/*` (with the sort nodes `FieldSort`, `RelationSort`, `AggregateSort` and `RelevanceSort`, `AggregateKey`, and `SearchContext`, which `FilterFlattener` builds from the filters as `FlatFilter` entries), `EngineFilterCondition` and `EngineSort` in `src/Text/Engine/`, `src/Actions/OperationNode.php` and `src/Mutations/*` (`MutationNode`, `MutationContext`, `Contextual`, `MutationAwareRule`), and `src/Validation/{ValidationContext,Vocabulary,ParsedRules,PayloadReader,InputSet,CompiledRules,MessageBook}.php`
- Request limits and resource keys — `src/Validation/{Limits,ListSizes,Depths,Totals,LimitGroup,NodeBudget,Cursor,Violation,Violations,Rejected,KeysGrammar,KeyList,KeyLookup,KeyRead,ExistingKey}.php` and `src/Http/Requests/Traits/ParsesResourceKeys.php` (the `resources` of the deletion requests, whose key query loads their rows; the operate parser reads the `resources` of the actions with the `Resources` target)
- Scramble extension — `src/Scramble/LomkitLaravelRestApiOperationExtension.php`, with `RelationMutationType` (a relation entry), `WritableFieldsType` (writable fields per operation), `RuleType` (the type a rule list implies), `TextSearchType` (`search.text`, `search.trashed` and the `@relevance` sort), `EngineTextType` (`search.text` of a `ScoutEngine` resource), `DetailsResponseType` (the details response) and `DetailsEntryTypes` (its entries)
- Generator stubs — `src/Console/stubs/*.stub`
