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
- **Searching outside HTTP**: `Rest::search(TicketResource::class)->as($user)->payload($search)` replays a client search payload from a job or a command, with the validation, perimeter and policies of the endpoint; `page()` returns the models, `render()` the endpoint body, `lazy()` every rendered row by chunks (exports).
- **Mutate**: batched create/update plus relation operations (`attach`, `detach`, `sync`, `toggle`, `delete` of child rows) in one request via `POST /{resource}/mutate`, which can return the written rows (`response`) and refuse an update of a row changed since the client read it (`versioned()`, `409`).
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

- **OpenAPI documentation**: Scramble documents every endpoint of the resources; the package registers its documentation itself.
- **Precognition**: live form validation (`Precognition: true` header) short-circuits the request after validation when `rest.precognition.enabled = true`.
- **Request size limits**: each list of a request accepts a limited number of entries, filters nest up to 1 level and includes and relation operations up to 3 levels, four totals bound one search and one request, and a filter value takes a limited number of characters, all set in `rest.limits` (`config/rest.php`): `sizes` for the lists, `depths` for the nesting of filters, includes and relations, `totals` for the four totals, `lengths` for the patterns and the other string values, and `sizes.rows` for the rows of one search page or mutation response. A value over its length is a `422` on that value. A list over its limit is one `422` on that list. `sizes.groups` bounds a response instead: the parts of a partition and the splits of a trend, 100 by default.
- **UTF-8**: every string value and every key of a request, query string included, must be valid UTF-8: another one is a `422` on its path (`The mutate.0.attributes.name field must be valid UTF-8.`; a key shows `?` in place of its invalid bytes), answered alone, before the rules of the request read the input.

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

### Translations

Every message that the package writes for a client (its validation texts, the action caps, the default denial, the 409 conflict) is a line under the `rest` namespace: `rest::validation.*`, `rest::actions.*`, `rest::errors.*`. The package ships `en` and `fr` and follows the locale of the request. `php artisan vendor:publish --tag="rest-lang"` copies the files to `lang/vendor/rest` to reword them or add a locale. Exception messages for developers stay English, and field labels stay the request paths unless `label()` or `attributes()` names them.

## Core concepts

### 1. Resource — `app/Rest/Resources/{Name}Resource.php`

A Resource declares the **shape of what the API exposes** for an Eloquent model. Generate one with:

```bash
php artisan rest:resource UserResource --model=User
php artisan rest:resource UserResource --model=User --generate   # one field per column of the users table, plus the relations that have a resource
```

Override these methods. They take no argument: a list that depends on the user reads `$this->request()->user()`. `fields()`, `relations()`, `scopes()`, `limits()`, `actions()`, `instructions()`, `text()` and `gates()` run once per request, the first time the package reads them, and the resource keeps nothing between requests:

- `fields()` — list of typed `Lomkit\Rest\Fields\*` objects the client may select / filter / sort / mutate on: `ID`, `Text`, `Number`, `Integer`, `Boolean`, `Date`, `DateTime`, `Enum`, `Json`, or an app type extending `Field`. Strings and duplicate names throw `InvalidFieldException`. `$this->definition()->fields()` returns a `FieldSet`; `->names()` gives the list of names. A `DateTime` value with an offset (`2026-10-12T08:00:00+02:00`) is converted to `config('app.timezone')` in filters, writes (pivot fields too) and inputs; a value without an offset is read in that zone, and a `Date` value keeps the day as sent.

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
  Text::make('delivery_address')->inputOnly(),         // never returned, 422 in selects, sorts, filters and aggregates
  DateTime::make('created_at')->selectable(false),     // not returned, 422 in selects, sorts, filters, min/max/avg/sum
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

  - Selectable fields (not `writeOnly()` or `inputOnly()`, not `selectable(false)`, and not `virtual()` unless it declares `resolveUsing()`) are the only ones `selects` accepts and the ones a row returns without `selects`, at every include level. `resolveUsing(fn (Model $model): mixed => ...)` gives a field its value: the callback receives the model of the row, runs once per returned row, and its value is sent in the type of the field, as a column value is (a `Json` field does not decode it). `requires(['first_name', 'last_name'])` lists the columns the callback reads; they load when the field is returned. A field that is not a column must be `virtual()`, and `$appends` never reach a row: return an appended value through `resolveUsing()`.
  - Sortable fields are the only ones `sorts` accepts: `Json`, `sortable(false)`, `writeOnly()` and `inputOnly()` fields and `virtual()` fields without `sortUsing()` are refused, and so are `selectable(false)` fields not declared `sortable()`. `sortUsing()` replaces the column order of its field (and makes a virtual or write-only field sortable); its callback receives the Eloquent query, on an include as well, and `asc` or `desc`. The other fields order by their qualified column (`posts.views`).
  - Filterable fields are the only ones `filters` accepts: `Json`, `filterable(false)` and `inputOnly()` fields, and `virtual()` or `writeOnly()` fields without `filterUsing()` are refused, and so are `selectable(false)` fields not declared `filterable()` (`The selected search.filters.0.field is invalid.`). The operators come from the field type, `nullable()` adds `is null` and `is not null`, and `filterable([...])` narrows them (an operator the type does not have throws `InvalidFieldException`). `filterUsing()`, a closure or a `Lomkit\Rest\Contracts\Filter` object, replaces the column condition of its field, also at the end of a relation path (`author.full_name`): it receives the Eloquent query, the operator as a string and the value as sent, and its conditions stay in their own group. The other fields compare their qualified column (`posts.views`, or the pivot table for a pivot field).
  - Searchable fields (`searchable()`) are the ones a text search reads, see "Text search" below. Only `Text` fields (and app types extending `Text`) can be searchable; `searchable()` on another type, a `virtual()`, a `writeOnly()` or an `inputOnly()` field throws `InvalidFieldException`, in any order of the calls.

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
  Text::make('delivery_address')->inputOnly(),         // validated, handed to the hooks, never written
  Enum::make('status')->offered([PostStatus::Draft, PostStatus::Published]), // the other cases are read and filtered, refused on writes
  Text::make('full_name')
      ->virtual()                                      // written through its callback only
      ->fillUsing(function (Model $author, mixed $value): void {
          $author->forceFill(['first_name' => Str::before((string) $value, ' '), 'last_name' => Str::after((string) $value, ' ')]);
      }),
  ```

  `inputOnly()` declares a resource field that is not a column: `mutate` validates its value by its type and rules, `creatable()`, `updatable()` and `readonly()` apply, and the value reaches `$entry->attributes` in `mutating()` and `mutated()` and the `Changes` of the `update` policy (`update`, `sync`, `toggle`), never the model (the draft of a `create` check does not hold it: refuse a value with rules or in `mutating()`). An entry that only sends input-only values runs its hooks and writes no column unless a hook sets one (no query, `updated_at` unchanged). The field is never selected, sorted, filtered, searched, aggregated or returned; `/details` and the OpenAPI document show it as a write-only field. It takes no `fillUsing()`, `resolveUsing()`, `filterUsing()`, `sortUsing()`, `searchable()`, `filterable()`, `sortable()` or `selectable()` (`InvalidFieldException`, in any order of the calls; `filterable(false)`, `sortable(false)` and `selectable(false)` are accepted); `virtual(false)` and `writeOnly(false)` do not undo it, `inputOnly(false)` does. `default()` does nothing on it, as on every resource field, and it has no effect on action, instruction or scope fields.

  `Enum::make(...)->offered()` takes a list of cases or a closure (`fn (PostStatus $status): bool => $status !== PostStatus::Archived`); without it, every case is offered. A case left out (a withdrawn case that old rows still hold) stays readable, filterable and sortable, and writes refuse it with `The selected {key} is invalid.` on its key: `mutate` attributes at every level, pivot fields, action, instruction and scope fields, Precognition included. The document lists the offered cases in the create, update, pivot and input schemas and every case in the records and filters; details add `offered` next to `values`. A case of another enum or a list that offers no case throws `InvalidFieldException` when the offered cases are first read. An app field type documents the values that writes accept with `toWriteSchema()` (`toSchema()` by default).

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
- `limits()` — allowed `limit` values (default `[10, 25, 50]`). The `$defaultLimit` property (`50`) sizes a page sent without `limit`, and `$includeLimit` (`10`) the rows of each parent row in an include of this resource sent without `limit`. Both must be values of `limits()`, or the resource throws `InvalidResourceException` when its limits are first read. `$maxTotal` (`null`: exact count) caps the count of a SQL search: with `public ?int $maxTotal = 10000;` the count reads at most 10,001 rows, and the pages answer `"total_capped": true` after `total` (then `10000`) when more rows match, `false` otherwise; it must be at least the largest value of `limits()`.
- `actions()` — `Lomkit\Rest\Actions\Action` classes (`CloseTickets::class`) or instances, each with its own route `/actions/{uriKey}`; read when the routes register, so keep the list the same for every user.
- `instructions()` — `Lomkit\Rest\Instructions\Instruction` instances exposed under the `instructions` search key.
- `text(): ?\Lomkit\Rest\Text\TextSearch` — the text search strategy of `search.text` (`Contains::make()`, `FullText::make()` or `ScoutEngine::make()`), `null` by default (no text search). See "Text search" and "Search engines" below.
- `gates(): array` — the names of extra policy methods that `search.gates` accepts next to the built-in abilities, `[]` by default. See "2. Gates" below.
- `alwaysSelect(): array` — the columns of the table that every search of the resource loads without returning them (`[]` by default): the columns that a policy, an accessor, an observer, a hook or `map()` reads.
- `rules(): array`, `createRules(): array`, `updateRules(): array` — resource-level rules applied during mutate, for rules that compare attributes and for nested keys (`metadata.source`). Put the rules of one attribute on its field instead. Each key gets one list: the field rules, then `createRules()` (on `create`) or `updateRules()` (on `update`, `sync`, `toggle`), then `rules()`; pipe strings are split first. The three methods are read once per request, so every entry shares the rule objects they return. For a rule that depends on the entry, use `\Lomkit\Rest\Mutations\Contextual::rule(fn (MutationContext $mutation) => Rule::unique(Post::class, 'title')->ignore($mutation->key))` or a rule object that implements `\Lomkit\Rest\Mutations\MutationAwareRule` (`setMutation(MutationContext $mutation): static`, called on a clone per entry); `MutationContext` holds the `operation`, `key` and `depth` of the entry. A `Rule::when()` condition reads the attributes of its entry. The same holds for field rules.
- `defaultOrderBy()` — default sort (defaults to the model key in descending order, `[$model->getKeyName() => 'desc']`); a column may name its table (`posts.views`).
- `detailsMeta(): array` — keys that the details response returns under `meta` (`{}` by default), computed on each details request for its user. See "`details` endpoint" below.
- `searchMeta(SearchContext $search, LengthAwarePaginator $page): array` — keys that a search page returns in its `meta` (`[]` by default), after `searched()`. See "Response" in the `search` payload below.
- Resource hooks (model-lifecycle), inside the endpoint's transaction: `mutating(MutationNode $entry, Model $model): void` and `mutated()` receive the entry of `payload()` (`operation`, `key`, `attributes`, `pivot`, `withoutDetaching`, `relations`, `type`), whose `attributes` hold the values of `inputOnly()` fields, which the model never receives. The attributes that the client sends are written after `mutating()`: a column that the hook fills must not also be a writable field, or the sent value overwrites the value of the hook; declare that column `readonly()`, `creatable(false)` or `updatable(false)`, or leave it out of `fields()`. The other hooks: `destroying(Model $model): void`, `destroyed`, `restoring`, `restored`, `forceDestroying`, `forceDestroyed` receive the row. They take no request: read it with `$this->request()`. `mutating`/`mutated` run for each row that an entry writes, at every level, on the resource of that row: a `create`, an `update`, an entry with `attributes`, and an `attach`/`detach`/`sync`/`toggle` entry whose own `relations` change. They do not run for a target that is only linked (no `attributes`, no `relations`).
- Endpoint events (one per endpoint moment, typed payload, return `void`): `describing()`, `described(array $details)`, `searching(SearchContext $search)` (also for the search of an action or a metric; throw `ValidationException` for a 422 before any query), `searched(LengthAwarePaginator $page)`, `mutatingAll(array $entries)` (`MutationNode` list) and `mutatedAll(array $operations)` inside the mutate transaction, `operating(Action $action, ActionFields $fields)` and `operated(Action $action, ActionFields $fields, int $impacted)` around the chunks (inside the transaction of `ActionTransaction::Whole`; `respond()` after), `destroyingAll(Collection $models)` / `destroyedAll()` and the `restoring`/`forceDestroying` twins inside the deletion transaction. They run after the entry checks and validation, the events that receive rows after the row checks; a throw inside a transaction rolls it back. Read the request with `$this->request()`.
- Laravel events (`Lomkit\Rest\Events`, `Event::listen()`, every resource; filter with `$event->resource === TicketResource::class`): `ResourceMutated` (`resource`, `operations` as arrays), `ResourceDestroyed` / `ResourceForceDestroyed` (`resource`, `model` class, `keys`), `ResourceRestored` (`resource`, `models`), `ActionOperated` (`resource`, `action` class, `uriKey`, `fields` values by name, `impacted`, `batch`); `resource` is the class of the resource. Dispatched once per request after the commit (`ShouldDispatchAfterCommit`): never for a rolled-back write or a retried attempt; `ActionOperated` after a run that ends without error (a queued action: in the request, once the jobs are queued). Read-only: refuse a write in the resource events. A listener may implement `ShouldQueue`: the events serialize (`ResourceRestored` reloads its rows in the worker). `KeysOutOfReach` (`resource`, `model`, `endpoint` as an `Endpoint` case, `keys` and their payload `paths` such as `mutate.0.relations.tags.1.key`, at most 50 each with `capped`, `guard`, `user` identifier) is dispatched at once, during validation, when keys of a request are not in the `perimeterQuery()` of their resource (`mutate` entry and relation keys, the `resources` of an action, `destroy`, `restore`, `forceDestroy`, Precognition included): once per resource and request, before the `422`, which it does not change; keys refused for their type or a repeat dispatch nothing.

**Recording each written row**: write the record (a history row, say) from the resource hooks, which run inside the transaction of the endpoint: a throw in a hook or later in the request rolls back the write and the record together, and a deadlock retry (`rest.transactions.attempts`) runs the hooks again after rolling back what the failed attempt wrote. On `mutate`, use `mutated()`: `$entry->attributes` and `$entry->relations` (the nodes of each relation: `operation`, `key`, `pivot`, `relations`) say what the request changed for `$model`, whose row and relations are written by then; read the relation in `mutated()` for the rows now attached. Record a link to a target that is only linked, a moved child and a child `delete` from the entry of the parent row, since they run no hook of their own. On `destroy`, `restore` and `forceDestroy`, use `destroyed()`, `restored()` and `forceDestroyed()`, once per row. In an action, write the records inside `handle()`, in the transaction of its chunk or run (`ActionTransaction::None` opens none). The Laravel events run after the commit: keep them for work outside the database.

A model that uses `Laravel\Scout\Searchable` gives its resource no text search by itself: declare `text()` with `ScoutEngine::make()`, see "Search engines" below.

The Resource also has typed static properties (PHP requires the `string` type when you redeclare them):
- `public static string $model = Post::class;` — the Eloquent class (required: a resource without it throws `InvalidResourceException`). Read it with `PostResource::modelClass()`.
- `public static string $response = \Lomkit\Rest\Http\Response::class;` — override with a subclass of `Lomkit\Rest\Http\Response` to customise output (see "Custom Response").

Resources extending `app/Rest/Resources/Resource.php` (generated via `rest:base-resource`) may override:
- `perimeterQuery` — the rows that exist for the client (tenant scoping, soft-visibility, etc.), on every endpoint: apply the constraints to `$query` itself and return it; the package ignores the returned builder. It applies to searches and actions, at every level (a text search on a search engine takes its rows from the index perimeter instead), and limits the keys that `mutate` accepts, at the root and under relations, and the keys of the deletion endpoints: they are checked during validation, with one query per resource class, a key it leaves out is a `422` (`The selected mutate.0.key is invalid.`), and the rows it returns are the rows that the write changes. It reads the endpoint's request with `$this->request()`: branch on `$this->request()->endpoint()` to let one endpoint reach rows that the others leave out. A row the client may see but not change is a policy matter (`403`), not a perimeter one. A resource that does not override it shows every row to users who pass `viewAny`: `rest:check` reports it, unless the class (or a parent) carries `#[\Lomkit\Rest\Attributes\WithoutPerimeter]`.

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
| `GET` | `/users` | `details` | Schema for the resource: fields, limits and meta, the rest (relations, scopes, actions, metrics, instructions, rules, text search, gates, confirmations, authorizations) with `?with=` |
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

`GET /api/artworks` describes the resource. It lists what the declarations of the resource (`fields()`, `relations()`, `scopes()`, `actions()`, `metrics()`, `instructions()`, the rules methods, `text()` and `gates()`) return for the current request, which can depend on `$this->request()->user()`. It checks `viewAny`, then answers a base: `fields` (each with `name`, `label`, `type`, `nullable`, and `values` for an `Enum`, plus `offered` when `offered()` leaves cases out), `limits` and `meta`:

```json
{"data": {
  "fields": [
    {"name": "id", "label": null, "type": "id", "nullable": false},
    {"name": "title", "label": "Title", "type": "text", "nullable": false},
    {"name": "medium", "label": null, "type": "enum", "nullable": false, "values": ["oil", "ink", "watercolor"]}],
  "limits": [10, 25, 50],
  "meta": {}
}}
```

The query parameter `with` adds the rest, as a comma-separated list of sections (`actions`) and parts of a section (`actions.authorized`). A part adds its section with its base keys. A repeated value counts once, and an empty `with` answers the base. Any other value, a third level (`actions.fields.rules`) or a list (`with[]=`) answers `422` on `with`, with the accepted values in the message. Nothing that is not asked for is computed: without `authorizations`, `actions.authorized` or `metrics.authorized`, details calls no policy but `viewAny`.

| `with` | Adds |
| --- | --- |
| `fields.capabilities` | `select`, `sort`, `create`, `update` on each field |
| `fields.filter` | `filter.operators` on each field |
| `fields.rules` | `rules` (`all`, `create`, `update`) on each field |
| `fields.meta` | `meta` on each field |
| `relations` | each relation: `resource`, `relation`, `name` (plus `types` and `morph` on a `MorphTo`) |
| `relations.constraints` | `constraints` |
| `relations.operations` | `operations` |
| `relations.pivot_fields` | `pivot_fields`, on many-to-many relations only, with every key of a field entry |
| `actions` | each action: `name`, `uriKey`, `target`, `destructive`, `confirm`, `confirmation` |
| `actions.fields` | `fields`, the input entries of the action |
| `actions.authorized` | `authorized` (a policy call per ability of the actions) |
| `actions.meta` | `meta` |
| `metrics` | each metric: `name`, `uriKey`, `type`, `ranges` |
| `metrics.authorized` | `authorized` (a policy call per ability of the metrics) |
| `metrics.meta` | `meta` |
| `instructions` | each instruction: `name`, `uriKey`, `text` |
| `instructions.fields` | `fields`, the input entries of the instruction |
| `instructions.meta` | `meta` |
| `scopes` | each scope: `name` |
| `scopes.fields` | `fields`, the input entries of the scope |
| `scopes.meta` | `meta` |
| `rules` | the rules of the resource |
| `text` | the text search (`null` without one) |
| `gates` | the names that `search.gates` accepts |
| `confirmations` | `mutate`, `destroy`, `restore`, `forceDestroy` (these two when the model uses `SoftDeletes`): `true` when the resource declares the confirmation hook of that write |
| `authorizations` | the class-level abilities, for the user of the request (policy calls) |

Ask for what the screen shows:

```
GET /api/orders?with=fields.capabilities,fields.filter,scopes.fields,actions.authorized
GET /api/orders?with=fields.capabilities,fields.rules,relations.operations,relations.constraints,authorizations
GET /api/orders?with=actions.fields,actions.authorized
```

The first one serves a list screen (sortable and filterable columns, scopes, bulk action buttons), the second a create or edit form, the third the window of an action. With every value, and a `collector` morph-map alias:

```json
{"data": {
  "actions": [{"name": "Retire Artworks", "uriKey": "retire-artworks", "target": "search",
    "fields": [{"name": "notify", "label": null, "type": "boolean", "nullable": false, "default": false, "rules": ["boolean"], "meta": {}}],
    "meta": {}, "destructive": false, "confirm": null, "confirmation": false, "authorized": true}],
  "metrics": [{"name": "Artworks By Medium", "uriKey": "artworks-by-medium", "type": "partition", "ranges": [], "meta": {}, "authorized": true}],
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
     "constraints": {"required_on_creation": false, "prohibited_on_creation": false, "required_on_update": false, "prohibited_on_update": false}, "operations": ["create", "update", "attach", "detach"], "name": "BelongsTo"},
    {"resource": "App\\Rest\\Resources\\ExhibitionResource", "relation": "exhibitions",
     "constraints": {"required_on_creation": false, "prohibited_on_creation": false, "required_on_update": false, "prohibited_on_update": false}, "operations": ["create", "update", "attach", "detach", "sync", "toggle"], "name": "BelongsToMany",
     "pivot_fields": [
       {"name": "position", "label": null, "type": "integer", "nullable": true, "select": true, "sort": false, "filter": {"operators": ["=", "!=", ">", ">=", "<", "<=", "between", "not between", "in", "not in", "is null", "is not null"]}, "create": true, "update": true, "rules": {"all": ["nullable", "integer"], "create": [], "update": []}, "meta": {}},
       {"name": "wall", "label": null, "type": "text", "nullable": false, "select": true, "sort": false, "filter": {"operators": ["=", "!=", "like", "not like", "ilike", "not ilike", "in", "not in"]}, "create": true, "update": false, "rules": {"all": ["string"], "create": [], "update": []}, "meta": {}}]},
    {"resource": "App\\Rest\\Resources\\CollectorResource", "relation": "owner",
     "constraints": {"required_on_creation": false, "prohibited_on_creation": false, "required_on_update": false, "prohibited_on_update": false}, "operations": ["create", "update", "attach", "detach"], "name": "MorphTo",
     "types": {"collector": "App\\Rest\\Resources\\CollectorResource"},
     "morph": {"type_column": "owner_type", "key_column": "owner_id"}}],
  "rules": {"all": {}, "create": {"price": ["required", "min:0"]}, "update": {}},
  "text": {"fields": ["title"], "modes": ["words", "phrase"], "relevance": false, "trashed": false, "max_length": 255, "engine": null},
  "gates": ["view", "create", "update", "delete"],
  "confirmations": {"mutate": false, "destroy": false},
  "authorizations": {"viewAny": {"allowed": true, "message": null}, "create": {"allowed": true, "message": null}},
  "meta": {}
}}
```

- `fields` has one entry per field, in declared order. `label` is the field's `label()`, or `null` without one, in field, pivot field and input entries. With `fields.capabilities`: `select`, accepted in `search.selects` and returned; `sort`, accepted in `search.sorts`; `create` / `update`, writable on that operation. With `fields.filter`, `filter.operators`: the operators of `search.filters` on the field, `[]` when it is not filterable. With `fields.rules`, `rules.all` is the type rule, `nullable` and `rules()`; `rules.create` and `rules.update` are `creationRules()` and `updateRules()`. `Enum` entries add `values`, and `offered` (the values that writes accept) when `offered()` leaves cases out. There is no `default`: resource fields do not apply it.
- `rules` holds the resource rules (`rules()`, `createRules()`, `updateRules()`), keyed by attribute, as lists of strings.
- `relations`: `resource` is the class name of the related resource (absent on a `MorphTo` with several targets), `operations` lists the operations that the entries of the relation accept in `mutate`, as `acceptedOperations()` returns them (`create`, `update`, `attach`, `detach`, plus `sync` and `toggle` on many-to-many relations, less the operations left out with `onlyOperations()` or `withoutOperations()`, then `sync` on a `syncable()` relation, and `delete` last on a `deletable()` relation; `[]` on `HasOneThrough` and `HasManyThrough`): read them from it, not from `name`. `sync` tells what a `sync` entry of the relation does: `"replace"` on a many-to-many relation (the attached rows become the listed rows), `"link"` when the listed rows are only linked (a custom relation that lists `sync`, or a many-to-many relation without `detach`), `"check"` on a `syncable()` `HasMany` or `MorphMany` (see the `mutate` payload), `null` when the relation takes no `sync`. `name` is the short class name of the relation (or its `$name`). Many-to-many relations (every `PivotRelation`) add `pivot_fields`: `create` means `create` and `attach`, `update` means `update`, `sync` and `toggle`, `select` means returned in the `pivot` of an included row, `filter.operators` applies to `<relation>.pivot.<field>` filters, and `sort` is always `false`. When the model method is not an Eloquent many-to-many relation, `select` is `false` and `filter.operators` is `[]`. `MorphTo` adds `types` (each morph type, the morph-map alias or the model class, mapped to its resource class) and `morph` (`type_column`, `key_column`). Detect `pivot_fields`, `types` and `morph` by their presence, not by `name`.
- `text` is `null` without `text()`. Otherwise: `fields` (the searchable fields, `[]` on `ScoutEngine`), `modes`, `relevance` (`@relevance` accepted with a text), `trashed` (`search.trashed` accepted with a text), `max_length`, and `engine`, `null` on `Contains` and `FullText`. On `ScoutEngine`, `engine` holds `filters` (the `filterable()` attributes with `name`, `type`, `operators`, and `values` for an `Enum`; never the perimeter attributes), `sorts`, `max_sorts`, `max_page_size` and `window` (`null` when unbounded). With a filled text on `ScoutEngine`, `text.engine` replaces the `sort` and `filter` of the field entries, within the grammar of [Search engines](#search-engines).
- `gates` lists the abilities that `search.gates` accepts: `view`, `create`, `update`, `delete`, `restore` and `forceDelete` (these two when the model uses `SoftDeletes`), then the names that `gates()` declares. It is `[]` when `isGatingEnabled()` returns `false`.
- `authorizations` (with `with=authorizations`) maps each class-level ability that the routes check to `{"allowed", "message"}`: `viewAny`, `create`, the abilities of `abilities(Endpoint)` for each endpoint (`restore` and `forceDestroy` when the model uses `SoftDeletes`), then those of the actions and metrics (`entryAbilities()`), each once. With `actions.authorized` or `metrics.authorized`, each action or metric entry ends with `authorized`: whether the user passes the abilities that its route checks before reading the request. A refused one is listed with `false`; its route answers `403`. Row abilities stay in the `gates` of a search. With `isAuthorizingEnabled()` false, everything is allowed.
- `meta` holds what `detailsMeta(): array` of the resource returns, `{}` when the resource does not override it. It runs on each details request, for its user:

  ```php
  public function detailsMeta(): array
  {
      return ['label' => 'Artworks', 'default_sort' => ['field' => 'title', 'direction' => 'desc'], 'curator' => $this->request()->user()?->name];
  }
  ```

  `described(array $details)` receives the details as the request asked for them, with `meta`, and cannot change them.
- `actions`, `instructions` and `relations` are lists, also when a declaration method returns a keyed array. Maps are objects, also when empty (`"meta": {}`, `"all": {}`). Ignore unknown keys: later releases can add keys.
- A declaration error also throws on `details` when the request reads that declaration: `InvalidFieldException` for an `Enum` field without an enum class or cast (always), `InvalidTextSearchException` for the text search (`text`), `InvalidResourceException` for a `MorphTo` whose model method is not a `MorphTo` of that name or whose targets are not a list of distinct resources (`relations`) or for an invalid name in `gates()` (`gates`), `BadMethodCallException` for a many-to-many relation or a `MorphTo` without its model method (`relations`).

The `fields` of actions, instructions and scopes take `rules()` only: declaring `creationRules()` or `updateRules()` on them throws `InvalidFieldException` when the fields are built. They are described as input entries, which a frontend can render as a form:

```json
{"name": "since", "label": null, "type": "date", "nullable": false, "default": null, "rules": ["date"], "meta": {}}
```

`Enum` entries add `values`, and `offered` when `offered()` leaves cases out. Rules are strings: pipe strings are split, `Stringable` rules are cast to string, other rule objects are named by their snake class name (`enum`, `password`), and closures and anonymous rule objects are left out.

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
    "gates": ["create", "view", "update", "delete"],
    "page":  2,
    "limit": 10
  }
}
```

**Keys and shapes**: a key the package does not know inside an object of a search (a filter, a sort, an include, `text`…) is a `422` on that key, named as written (`The search.filters.0.operater field is prohibited.`), while such a key at the first level (the request body, `search` itself, a `response` block) is ignored, so a later release can add keys; every list of a search (`filters`, `nested`, `sorts`, `selects`, `scopes`, `aggregates`, `includes`, `instructions`, `gates`) must be a JSON list, and an object is a `422` on the list (`The search.filters field must be a list.`).
**Filter `field`** must reach a filterable field: a field of the resource, a dotted path through declared relations checked on the resource at its end (`posts.comments.body`), or a filterable pivot field right after a `BelongsToMany`, `MorphToMany` or `MorphedByMany` relation (`roles.pivot.expires_at`). Through a `MorphTo` relation with one target (`commentable.title`), the field is read on the declared target resource, and only the rows of that target's type match: other stored types never match nor fail. Through a `MorphTo` with several targets, a dotted path is a `422`: use a relation group with `types`. The `perimeterQuery()` of the resource of each relation on a path (the declared target for a `MorphTo`) applies to its related rows, each in its own group, and each resource on the path needs `viewAny`. A path goes through at most `rest.limits.depths.includes` relations (default `3`; a `pivot` segment does not count): a longer one is a `422` on its `field`, `The search.filters.0.field field goes through more relations than the limit of 3.` Another field is a `422` under `search.filters.{index}.field`.
**Filter `operator`** depends on the field type (`Lomkit\Rest\Fields\Filtering\FilterOperator`): `ID` and `Enum` take `=`, `!=`, `in`, `not in`; `Text` takes `=`, `!=`, `like`, `not like`, `ilike`, `not ilike`, `in`, `not in`; `Number` and `Integer` take `=`, `!=`, `>`, `>=`, `<`, `<=`, `between`, `not between`, `in`, `not in`; `Boolean` takes `=`, `!=`; `Date` and `DateTime` take `=`, `!=`, `>`, `>=`, `<`, `<=`, `between`, `not between`; `Json` takes none. `nullable()` adds `is null` and `is not null`, and `filterable([...])` narrows the list. Without `operator` a filter uses `=`, and `operator` is required on a field that does not accept `=`. Another operator is a `422` under `search.filters.{index}.operator`.
**Filter `value`** is validated per operator: a value of the field type for `=`, `!=`, `>`, `>=`, `<`, `<=`; a string of at most `rest.limits.lengths.patterns` characters (default `255`) for `like`, `not like`, `ilike`, `not ilike`; a non-empty list of at most `rest.limits.sizes.values` values (default `100`) for `in` and `not in`; exactly two values for `between` and `not between`; no value for `is null` and `is not null`. A string value, and each string of a list, has at most `rest.limits.lengths.values` characters (default `1000`); a pattern takes the smaller of the two lengths. `=` or `!=` with `null` is a `422`: use `is null` or `is not null`. `like` is case-sensitive on PostgreSQL, follows the column collation on MySQL and ignores the case of ASCII letters on SQLite; `ilike` ignores case, except on MySQL columns with a binary or case-sensitive collation, and only for ASCII letters on SQLite. On SQLite a `Date` filter compares the day of the column, whether it holds `2026-02-10` or `2026-02-10 00:00:00`.
**Filter `type`**: `and` (default) or `or`. Use `nested` to group filters with their own logical operator, up to `rest.limits.depths.filters` levels (default `1`; each `nested` key is one level); a deeper `nested` is a `422` on its key, and nothing below it is validated. With `Contains` and `FullText`, filters keep this whole grammar next to `text`. With `ScoutEngine` and a filled text, filters take its `filterable()` attributes, `and` only, without `nested`, relation paths or relation groups (see "Search engines").
**Relation groups** (`{"relation": "posts", "exists": false, "nested": [{"field": "status", "value": "draft"}]}`) filter on the related records of one relation. With `exists` `true` (default), the record needs at least one related record that matches every filter of `nested`. With `false`, it needs none. Without `nested`, or with `[]`, any related record counts. One group applies all its filters to the same related record, where two dotted filters (`posts.status`, `posts.featured`) can match two different posts. A dotted filter keeps its meaning (`posts.status` `!=` `draft`: has a post that is not a draft). `nested` takes the fields of the related resource, with `type`, groups, relation paths and relation groups. The related resource's `perimeterQuery()` applies in its own group, and the related resource needs `viewAny`. The keys are `relation`, `exists`, `type` and `nested`. Another key next to `relation` is a `422` on that key. `relation` must name one relation of the resource (a dotted path is a `422`): reach a deeper relation with a relation path in `nested` (`{"field": "comments.body", ...}`), or with a relation group there when `rest.limits.depths.filters` is `2` or more. `exists` must be `true` or `false`. Its `nested` is one level of `rest.limits.depths.filters` (a group without `nested`, or with `[]`, fits at any depth), takes `sizes.filters` and counts in `totals.filters`. Through a `MorphTo`, the group takes `types`, one filter list per morph type (`{"relation": "labelable", "types": {"book": [...]}}`); `nested` is a `422` with several targets; `exists: false` also keeps the rows of other types and without a type. A pivot field of the group's relation (`pivot.position`) is a `422` in `nested`: use `tags.pivot.position` from the owner. Includes, aggregates and actions accept relation groups. A `ScoutEngine` search with a filled text does not.
**Scope `parameters`** is a positional list: each value binds to the scope field at the same position, is validated by its rules, cast to its type and filled from its `default()`. A position is required unless its field has a default; a missing, extra or wrongly typed parameter is a `422` under `search.scopes.{index}.parameters`. A rule that names another field of the same scope, such as `gte:min`, compares with the parameter at that field's position. Scopes are prohibited next to a filled text on a `ScoutEngine` resource.
**`selects`** lists the selectable fields to return (`writeOnly()` and `selectable(false)` fields, and `virtual()` fields without `resolveUsing()`, are a `422` under `search.selects.{index}.field`); without it a row holds every selectable field of its resource. Include `selects` use the related resource's fields. A search loads only the columns its rows need: the returned fields, the key, their `requires()`, `alwaysSelect()` and the keys of the includes and of the relations the model loads itself (`$with`, a `with()` in a scope, a global scope or an instruction); `rest.search.select_columns` set to `false` loads every column.
**`sorts`** apply in order and accept the sortable fields of the resource (`Json`, `sortable(false)`, `writeOnly()` and `virtual()` fields without `sortUsing()`, and `selectable(false)` fields without `sortable()`, are a `422` under `search.sorts.{index}.field`). `direction` is `asc` or `desc` (`Lomkit\Rest\Fields\SortDirection`), `asc` when omitted. A sort also takes a field of a to-one relation (`author.last_name`: `BelongsTo`, `HasOne`, `HasOneThrough`, `MorphOne` and their of-many forms; never `MorphTo`, a to-many relation or a deeper path) that `max` accepts (sortable by its own column, not `virtual()`, `writeOnly()` or `Boolean`), or the key of an aggregate of the same level (`posts_count`), its alias included, when that aggregate runs over a relation to many with no field or on a field that is not a key (not the related model key, an `ID` field, or a column that relates rows such as a foreign key): `author_count` on a `BelongsTo`, `posts_max_id` or `posts_sum_author_id` is a `422`. Both order by a subquery: the greatest related value within the related `perimeterQuery()`, or the aggregate computed again. They work at every level and within an include `limit`, and the relation needs `viewAny`. A missing value sorts as null, where the database puts it: first in ascending order on SQLite and MySQL, last on PostgreSQL. Without `sorts`, `defaultOrderBy()` applies, after the relevance order when the search has a text and its strategy orders by relevance. Every SQL order ends with the model key, descending, unless it already holds it: rows with equal values keep one order across pages and within an include `limit`; a search engine keeps its own order of ties. `@relevance` sorts by relevance (`desc` when `direction` is omitted) next to the other sorts, only with a filled text and a strategy that orders by relevance (`FullText` on MySQL, MariaDB or PostgreSQL), never in includes. With `ScoutEngine` and a filled text, sorts take its `sortable()` attributes, or `@relevance` alone for the engine order.
**Aggregate `type`** is required, one of `min`, `max`, `avg`, `sum`, `count`, `exists` (`Lomkit\Rest\Fields\AggregateFunction`). `field` is required for `min/max/avg/sum`, omit for `count/exists`. `min`/`max` take a field of the related resource that is sortable by its own column (`isSortableByColumn()`: sortable by default or `sortable()`, not `virtual()`, not `writeOnly()`, a `selectable(false)` field only with `sortable()`; `sortUsing()` does not count, so a `Json` field with `sortUsing()` is refused) and is not a `Boolean` (an app type extending `Boolean` included), on every database; an `ID` with UUID keys (`HasUuids` or `keyType('uuid')`) works for `min`/`max` on every database and returns the same string (PostgreSQL reads it as text); `avg`/`sum` also need a `Number` field (`Number`, `Integer` or an app type extending `Number`). Another field is a `422` under `search.aggregates.{index}.field`. An optional `alias` renames the response key, which is `{relation}_{type}_{field}` otherwise, with the field name in lower case (`stars_max_rate`, `stars_count`). The alias looks like an identifier (`^[A-Za-z_][A-Za-z0-9_]*$`, at most 255 characters), and the key, alias or not, must differ from the fields and relations of the resource, from the other keys of includes and aggregates of the same search, from the gates key and the `@more` key, from the type key inside a `MorphTo` include and from the pivot key inside a many-to-many include: a clash is a `422` under `search.aggregates.{index}.alias`, and under the `alias` of the include it clashes with. `relation` names a relation or a path of relations (`tickets.tags`, at most `rest.limits.depths.includes` relations): the aggregate counts each row at the end of the path once, applies the `perimeterQuery()` of every level and needs `viewAny` on each, its `filters` take the fields of the last level, and its key joins the relations (`tickets_tags_count`). A `MorphTo` relation, at the end of a path or inside it, is a `422`: aggregate a relation of a `MorphTo` target inside an include.
**`page`** is an integer from `1` to `intdiv(PHP_INT_MAX, L) + 1`, L being the largest value of `limits()` (`184467440737095517` by default), so that the offset stays an integer; `limit` must be one of the resource's `limits()`; without it a page holds `$defaultLimit` rows.
**`includes`** accept `relation`, `alias`, `filters`, `sorts`, `scopes`, `instructions`, `aggregates`, `selects`, `gates`, `limit` and `includes`; `page`, `text` and `trashed` are a `422`. They nest up to `rest.limits.depths.includes` levels (default `3`), and each relation of a dotted include counts toward it (`author.posts` takes two levels). Includes and aggregates always run in SQL, so they are validated against the related resource's fields and operators, also next to `text`; a missing or undeclared `relation` is a `422`. A row returns only the relations the search includes: a relation that the model loads itself (`$with`, a hook) stays loaded for policies, hooks and `map()` but is not returned. A `MorphTo` include returns each target with its type first, under `@type` (`rest.morph.type_key`): the morph-map alias or the class name of its model. With several targets, set the search of each type under `types` (`{"relation": "labelable", "types": {"book": {"selects": [...]}}}`); without `types` every target loads and needs `viewAny`. A row whose stored type is not a target, or not a listed type, returns `null`, with no query. Filters, aggregates and nested includes apply on the target query, and it comes after the includes without an alias in the row. An include returns at most its `limit` rows for each parent row, or the `$includeLimit` of its resource (`10` by default) without `limit`, the first ones in its `sorts` or else in the resource's `defaultOrderBy()`. A row whose level includes a to-many relation (`HasMany`, `MorphMany`, `BelongsToMany`, `MorphToMany`, `MorphedByMany`, `HasManyThrough`) ends with `@more` (`rest.search.more_key`): the keys of those includes that have more rows than they returned, `[]` when none; each such include reads one more row per parent and drops it before `searched()`, the gates and `map()`. The relation a dotted include goes through (`posts` in `posts.comments`) is limited the same way, and applies its resource's `perimeterQuery()` like any include: a row it hides is not loaded, nor the rows below it, and an owner it hides is `null`. A `BelongsTo` or `MorphTo` include loads the one related row of each parent row, with no limit and no order: `limit` and `sorts` there are a `422` (`The search.includes.0.limit field is prohibited: this relation loads at most one record per parent.`).
**Include `alias`** renames the response key for that include, which lets the same relation appear several times under different constraints. It must look like an identifier (`^[A-Za-z_][A-Za-z0-9_]*$`), be unique across the includes, and not collide with a field, a relation, the key of an aggregate or of another include, the gates key of the resource, the type key inside a `MorphTo` include, or the pivot key inside a many-to-many include. An include without alias whose key is the name of a field needs an alias. Two includes without alias may not load the same relation at one level: the same relation twice is a `422` under the `alias` of each (`The search.includes.1.alias field is required, because the key posts is already used.`), and a dotted include that another include already loads (`posts.comments` twice, or next to `posts` with `comments` in its `includes`, which then needs an alias) is a `422` under its `relation`. A dotted include next to an include of its first relation (`posts` and `posts.comments`) loads both. It is used verbatim — no snake_casing — and is rejected on a dotted relation path (use a nested include there).
**Instruction `alias`** names an entry of `instructions`, which lets a search send one instruction several times (`{"name": "odd-even-id", "alias": "odd_ids", "fields": [...]}`). Each entry of one `instructions` list needs its own key, its `alias` or else its `name`: the same instruction twice without an alias is a `422` under `search.instructions.{index}.alias` (`The search.instructions.1.alias field is required, because the key odd-even-id is already used.`), and an alias used by another entry, as its alias or its name, is a `422` too (`The search.instructions.1.alias is already used by another instruction.`). It must look like an identifier (`^[A-Za-z_][A-Za-z0-9_]*$`, at most 255 characters). The rule holds at every level: includes, the search of an action and of a metric.
**`gates`** asks the server to evaluate the listed policy abilities per row and embed the result under the configured key (default `gates`). Each answer is `{"allowed": bool, "message": string|null}`, the message coming from a `Response::deny('...')` of the policy, and the `create` answer goes to `meta`. A row without answers (`"gates": []`, or `create` alone) returns `{}`. It takes `view`, `create`, `update`, `delete`, `restore`, `forceDelete` and the gates that the resource of the level declares in `gates()`, each once. `restore` and `forceDelete` need a model that uses `SoftDeletes`. `viewAny`, another name (`restore` or `forceDelete` on another model included) or a repeated one is a `422` under `search.gates.{index}`, and `create` is a `422` inside an include, as is `gates` in an include whose resource turns its gates off. `update`, `delete`, `restore` and `forceDelete` answer `{"allowed": false, "message": null}`, without calling the policy, for a row that their endpoint would refuse with a `422`: a search engine page row that `perimeterQuery()` leaves out (one query per page checks the page), or a trashed row for `update` and `delete` unless `perimeterQuery()` takes trashed rows in. `perimeterQuery()` runs with the request of the response: a condition that a gate must follow on one endpoint belongs in the policy, not in a `$this->request()->endpoint()` branch.
**`text`** runs the text search of the resource, see "Text search" below: `{"value": "desk lamp", "mode": "phrase"}`, `value` at most 255 characters. A blank, `null` or missing `text.value` runs the normal search and returns the normal list. A resource that declares no `text()` refuses `text` with a `422`. `viewAny` is checked before any search runs.
**`trashed`** (`with` adds the soft-deleted rows, `only` returns them alone) works with and without `text`, on a model that uses `SoftDeletes` only (a `422` otherwise), and not in includes. Next to a filled text on a `ScoutEngine` resource, it also needs `scout.soft_delete` set to `true`. `text.trashed` is a `422`.
**List limits** (`rest.limits`, at every level of the search): `sizes.filters` (default `100`) applies to `search.filters`, to each `nested` list and to the `filters` of includes and aggregates; `totals.filters` (default `500`) counts every filter of one search, at every depth and in its includes and aggregates; `totals.relations` (default `20`, at least `1`) counts the relation filters of one search the same way: each dotted field path and each relation group, each one a subquery; `totals.patterns` (default `10`, at least `1`) counts the `like`, `not like`, `ilike` and `not ilike` filters of one search the same way; `sizes.values` (default `100`) applies to the values of `in`, `not in`, `between` and `not between`. `sorts`, `selects`, `scopes`, `aggregates`, `includes`, `instructions` and the `fields` of an instruction have their own limit in `sizes`; `gates` takes each ability once. Next to a search engine text, `sizes.sorts` also stays within what the engine runs. `totals.entries` (default `1000`) counts the entries of every list that takes a `rest.limits.sizes` value in one request, `gates`, `mutate` and the `fields` of actions included, except `resources`; the `parameters` of a scope keep their own limit, one value per field of the scope. A list over its limit is a `422` on its own key (`The search.filters field must not have more than 100 items.`), and nothing under it is validated or checked. A total gives `The search.filters.0.nested field goes over the limit of 500 filters in one search.`, `... goes over the limit of 20 relation filters in one search.`, `... goes over the limit of 10 pattern filters in one search.` or `... goes over the limit of 1000 list entries in one request.` on the list that goes over it; the relation and pattern filter totals go on the `filters` list of the search, include or aggregate.
**Response rows** (`rest.limits.sizes.rows`, default `10000`, at least `1`): before any query, a search page counts the rows it can render from the limits it asks for: the page limit, and for each to-many include its `limit` (or `$includeLimit`) for each parent row, nested includes multiplying; a to-one include counts one row for each parent, a `MorphTo` include its type with the most rows. The `response` of a mutation counts one record for each entry of `mutate`. Over it, the first key that takes the count over the limit gets a `422` (the `limit` of an include, its `relation` when it takes the default, `search.limit`, `search` or `response`): `The search.includes.0.includes.1.relation field takes the response over the limit of 10000 rows: it can render up to 10550 rows.` Action searches, metrics and `Rest::search()->lazy()` do not count rows.

**Response** is Laravel's paginator JSON. A `Number` or `Integer` field returns a number, also for the numeric string of a `decimal` cast (`25000.5`), a `Json` field returns an empty array as `{}`, and, on a column without cast or accessor, a `Boolean` field takes the `boolean` cast and a `DateTime` field the `datetime` cast, so they return `true` or `false` and the date through `serializeDate()` of the model (`2026-02-10T09:30:00.000000Z` by default). `meta` is `{}` when it holds nothing, and so is the `pivot` of a many-to-many row without pivot fields:

```json
{
  "current_page": 1,
  "data":        [{"id": 1, "name": "...", "gates": {"authorized_to_update": {"allowed": true, "message": null}}}],
  "from": 1, "to": 50, "per_page": 50, "last_page": 4, "total": 187,
  "meta":        {"gates": {"authorized_to_create": {"allowed": true, "message": null}}}
}
```

`meta` also holds what `searchMeta(SearchContext $search, LengthAwarePaginator $page): array` of the resource returns, after the `create` gate, and under `instructions` what `searchMeta(InstructionFields $fields): array` of each instruction at the root of the search returns, keyed by its `alias` or else its name (an instruction that returns `[]` adds nothing; the instructions of an include add nothing). The resource method runs after `searched()`, with the same page, on the database and on a search engine, under the same column check: a column that the search did not load throws `MissingColumnException`. A `searchMeta()` of the resource that returns the gates key or `instructions` throws `InvalidResourceException`. Only the search page has it, not the rows of a mutation, an action or a deletion:

```php
public function searchMeta(SearchContext $search, LengthAwarePaginator $page): array
{
    return ['read_at' => now()->toIso8601String()];
}
```

## Searching outside HTTP

`Rest::search()` replays a search payload that a client built, outside its request, for a given user: a saved search that a scheduled job sends each morning, a console command that reruns it. It is not a query API for your own code: use Eloquent there.

```php
use Lomkit\Rest\Facades\Rest;

$search = Rest::search(TicketResource::class)
    ->as($user)                 // required; as(null) is a guest; as($user, 'api') names the guard
    ->payload($savedSearch);    // the value of the "search" key of the endpoint body

$page = $search->page();        // LengthAwarePaginator of the models, includes cut to their limits
$body = $search->render(page: 2); // the endpoint body as an array; json_encode($body) is the endpoint content
```

- The payload goes through the validation of the endpoint: a failure throws `ValidationException` with the endpoint keys and messages (`search.filters.0.field`). `viewAny` is checked first and a denial throws `AuthorizationDenied`. `searching()` and `searched()` run as on the endpoint.
- Without `as()`, the search throws `Lomkit\Rest\Exceptions\SearchRunException`. The given user is `$this->request()->user()` in `perimeterQuery()`, the declarations and the hooks at every include level, and the user of the policies and the decision cache; the logged-in user of the application is never used, and is not changed.
- `page:` overrides the `page` of the payload and is validated as `search.page`. The objects that stay `{}` when empty (`meta`, `pivot`, `gates`) are `stdClass` instances in the array of `render()`.

`lazy(chunk: 500, max: null)` reads every row, for an export of what a user sees or a job over a saved search:

```php
$rows = Rest::search(TicketResource::class)
    ->as($request->user())
    ->payload($request->input('search', []))
    ->lazy();                   // validates, checks viewAny, runs searching() and reads the keys now

return response()->streamDownload(function () use ($rows): void {
    $file = fopen('php://output', 'w');

    foreach ($rows as $row) {   // the rows load and render by chunks of 500 keys
        fputcsv($file, [$row['id'], $row['title'], $row['status']]);
    }

    fclose($file);
}, 'tickets.csv');
```

- A `LazyCollection` of rendered rows: each one is the row of the endpoint `data` (fields, includes cut to their limits with `@more`, `gates`). Its rows are the rows of every page of the endpoint, in order, each once.
- The keys are read once, in the order of the search; includes and aggregates load per chunk; the key query selects the model key only, so only the keys of all rows stay in memory. A row deleted after its key was read is skipped. Each iteration loads the rows again.
- `max` keeps the first `max` rows. `limit` and `page` of the payload are ignored. `searched()` does not run. An override of `Resource::paginate()` does not apply to `lazy()`.
- A text search on a search engine (`ScoutEngine`) throws `SearchRunException`, which names the result window of the engine: search without text or use `page()`. A `chunk` below 1 or a negative `max` throws it too.

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
  - MySQL and MariaDB FULLTEXT: no stemming (`lamps` does not find `lamp`). Words shorter than `innodb_ft_min_token_size` (3 by default) and the InnoDB stopwords (`the`, `with`, ...) are not indexed: `words` ignores them and `phrase` skips them. In `prefix` each word must start an indexed word of the row: `l` and `la` find `lamp`, but `the lam` misses `The lamp` because `the` is not indexed. In `websearch` such a word is required and makes the search return nothing; lower `innodb_ft_min_token_size` or change the stopword list, then rebuild the indexes. A `websearch` of excluded words only returns the rows without these words, as on PostgreSQL. InnoDB only sees committed rows, so a test wrapped in a transaction (`RefreshDatabase`) finds none of the rows it wrote.
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

- `filterable()` takes fields (`ID`, `Integer`, `Number`, `Boolean`, `Text`, `Enum`, exactly these classes, without `filterUsing()` or `sortUsing()`) or the name of a field of the resource (a `selectable(false)` field needs `filterable()`; the attributes of `perimeter()` take it as it is). Names match `^[A-Za-z_][A-Za-z0-9_]*$`, and `__soft_deleted` is reserved. `sortable()` names `filterable()` attributes. The second argument of `perimeter()` lists attributes that only the perimeter uses: clients never filter or sort on them. A declaration error throws `InvalidTextSearchException` naming the resource when the resource is first searched in a request.
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
| `operation` | string | all | `create`, `update`; in `relations` also `attach` and `detach`, plus `sync` and `toggle` on many-to-many relations, `sync` on `HasMany` and `MorphMany` relations that declare `syncable()`, and `delete` on child relations that declare `deletable()`, less the operations that the relation leaves out with `onlyOperations()` or `withoutOperations()` |
| `attributes` | object | create / update / sync / toggle | Field values; only the fields the operation can write, validated by the field rules and the resource rules |
| `key` | int / string / array | update / attach / detach / sync / toggle / delete | Target model id(s), typed like the resource's `ID` field; a list only in an entry of a to-many relation |
| `version` | string | update, on a `versioned()` resource only | The `updated_at` the client read, as the search returns it; another value answers `409` and writes nothing |
| `relations` | object | all but `delete` | Map of `relationName → [operation objects]` (recursive) |
| `pivot` | object | entries of many-to-many relations only: `create`/`update`/`attach`/`sync`/`toggle` (refused on `detach`) | Pivot values; only the pivot fields the operation can write, validated by their rules |
| `without_detaching` | bool | `sync` | When `true`, behaves like `syncWithoutDetaching`; required and `true` on a many-to-many relation that does not accept `detach` |

An unknown key in an entry is a `422` on that key (`The mutate.0.atributes field is prohibited.`), and so are `pivot` outside an entry of a many-to-many relation and `type` outside an entry of a `MorphTo` relation. A `MorphTo` entry sends `type` (its morph type), required with several targets. `mutate`, to-many relations and `key` lists must be JSON lists (`The mutate field must be a list.`). Messages name `mutate` keys as written (`mutate.0.without_detaching`), unless a field's `label()` or the request's `attributes()` names them. `$request->payload()` returns the parsed entries as `Lomkit\Rest\Mutations\MutationNode` objects once the request passed validation, and the endpoint writes them.

Limits (`rest.limits`): `mutate` takes at most `sizes.mutate` entries (default `100`), each to-many relation at most `sizes.relation_entries` entries (default `100`), each key of a `delete` entry counting as one, and a `key` list at most `sizes.values` keys (default `100`). `relations` nest up to `depths.mutations` levels (default `3`): a deeper `relations` is a `422`, `The mutate.0.relations.products.0.relations.category.relations.products.0.relations field is prohibited.`. In `totals.entries`, a relation list and the `key` lists of its entries count once for each row of the parent entry, nested levels multiplying: under an entry with `"key": [1, 2, 3]`, `"tags": [{"operation": "attach", "key": [4, 5]}]` counts 3 for the list and 6 for the keys. A `sync` detaches at most `totals.entries` attached rows of each parent row: one that would detach more is a `422` on its `key`, and nothing is written.

Writes go through the fields:

- `create` accepts the creatable fields, and `update`, `sync` and `toggle` accept the updatable fields (`Lomkit\Rest\Fields\MutationOperation::writeOperation()` maps the operation). Fields are creatable and updatable by default; `ID` and `readonly()` fields are not, so declare `->creatable()` on a key the client sends (a UUID). `writeOnly()` and `inputOnly()` fields are accepted (an `inputOnly()` field needs no `fillUsing()` and is never written on the model); a `virtual()` field only with `fillUsing(Closure(Model, mixed): void)`, which runs after the other fields of the entry. Another key is a `422` under `{entry}.attributes`: `The created_at key of the mutate.0.attributes field is not writable on create.` (`on update.` for `sync` and `toggle`), with or without Precognition.
- Each attribute is validated by its field: the type's base rule (`null` refused unless `nullable()`, arrays refused except on `Json`), `rules()`, and `creationRules()` or `updateRules()`. A rule naming another declared field (`required_if:featured,true`, `after_or_equal:published_on`) compares with that attribute in the same entry.
- A `Json` field works with or without a JSON cast on the model. When the model writes the attribute as it is (no cast, or a `string`, `encrypted` or `AsStringable` cast, and no set mutator), the field writes `json_encode()` of the value and the response returns the decoded column. Any other cast (`array`, `json`, `collection`, `AsArrayObject`, `AsCollection`, `AsFluent`, `AsVector`, a cast class of the app) or a set mutator (`setXAttribute()`, an `Attribute` with `set`) receives the value as sent and does both. A `fillUsing()` callback receives the value as sent. A `Json` pivot field does the same with the casts and mutators of the pivot class, so a `json` pivot column works on the default `Pivot`.
- In a nested write, the keys the relation sets win over the entry's attributes: the parent key and morph type (`getMorphClass()`, so morph map aliases) of `HasOne`, `HasOneOfMany`, `HasMany`, `MorphOne`, `MorphOneOfMany`, `MorphMany`, and the foreign key and morph type that `BelongsTo` and `MorphTo` associate. The parent relation's keys win over the entry's own `BelongsTo` or `MorphTo`, and the `BelongsTo` and `MorphTo` keys are read after the `mutating()` hook, so a key the hook associates is kept.
- Under `HasOne`, `HasOneOfMany`, `HasMany`, `MorphOne`, `MorphOneOfMany` and `MorphMany`, `update` and `detach` reach only rows whose foreign key (and morph type) names the parent; `attach` may move a row of another parent. `attach`/`create` under `HasOne` or `MorphOne` is a `422` on the relation entry while the parent has a related row (`The mutate.0.relations.loan field is prohibited: this relation already holds a record. Detach it first.`) unless an earlier entry detaches it (a relation that does not accept `detach`, or whose foreign key or morph type does not accept null, ends the message with what it accepts instead: `... and does not detach it. Update that record instead.`, `Delete it first.` on a `deletable()` relation, or both); the of-many forms are exempt. Under `BelongsTo` and `MorphTo`, `detach` names the current owner (and `type`), never under a created row. `detach` is a `422` on its `key` when the foreign key (or the morph type) does not accept null (`The mutate.0.relations.team.key field is prohibited: this relation cannot be detached, its link column does not accept null.`): withdraw `detach` from such a relation. Other keys are a `422` `The selected ... is invalid.`. The checks follow the entries in write order, during validation. A `MorphMany` detach clears the morph type.
- `delete` removes child rows in the transaction of the mutation: `{"operation": "delete", "key": [102, 103]}` (a key, or a key list under a to-many relation). A `HasOne`, `HasOneOfMany`, `HasMany`, `MorphOne`, `MorphOneOfMany` or `MorphMany` relation accepts it once it declares `->deletable()` (`HasMany::make('lines', LineResource::class)->deletable()`); without it, at the root of `mutate` and on `BelongsTo`, `MorphTo` and many-to-many relations, it is a `422` (`The selected mutate.0.relations.lines.0.operation is invalid.`). A `delete` entry takes no `attributes` and no `relations` (`422`). Each key names a row that the related resource's `perimeterQuery()` returns and that is a child of the parent when the entry is written, else `The selected ... key is invalid.`; a deleted row that another entry of the request also names is a `422` on the `key` of the `delete` (`The mutate.0.relations.lines.1.key field has a duplicate value.`). Before any write, each row is checked with the `delete` ability of the related resource (as on `destroy`) and with the link check of `detach` (the relation's `detach` ability, else `update` on the parent): a refusal is a `403` naming the row, and nothing is written. The row goes through the related resource's `performDelete()`: a `SoftDeletes` model is soft deleted, never force deleted, and the model events run. `mutatingAll()`, `mutatedAll()` and `ResourceMutated` see the deletions; the `destroying()`, `destroyed()`, `destroyingAll()` and `destroyedAll()` hooks of the `destroy` endpoint do not run, so put a rule that holds on both endpoints in the policy or in a model observer.
- A `HasMany` or `MorphMany` relation accepts `sync` once it declares `->syncable()` (`HasMany::make('lines', LineResource::class)->deletable()->syncable()`). On such a relation, `sync` checks a complete list and writes nothing: it lists the children to keep, and the entries next to it write the changes. `[{"operation": "sync", "key": [101, 102, 103]}, {"operation": "update", "key": 101, "attributes": {"quantity": 3}}, {"operation": "delete", "key": 104}, {"operation": "detach", "key": 105}, {"operation": "create", "attributes": {"product_id": 7}}]` keeps 101 to 103, updates 101, deletes 104, detaches 105 and creates a line; the removals are the usual `delete` (needs `deletable()`) and `detach` entries, with their policies, link checks and limits. A relation list takes one `sync`, next to `create` entries, `update` entries of listed keys and `delete` or `detach` entries of other keys, else a `422`; the `sync` takes `key` alone, a key or a list (`attributes`, `relations` and `without_detaching` are a `422`). Each key is a current child; under a parent created in the request only `[]` is valid, and under a key list of parents each listed key must be a child of every parent, so in practice only `[]`. A list holds at most `limits.sizes.values` keys (`100`): a parent with more visible children cannot be synced (`422` or `409`) until the limit is raised. A row that another entry of the request names and whose parent is the synced one must be listed or removed in the list, else a `422` on the `key` of the sync; an entry anywhere in the request that deletes, detaches or moves a row that the sync keeps is a `422` on its `key` (`The mutate.1.relations.lines.0.key field removes or moves a related row that a sync entry of its parent keeps.`). Inside the transaction, after the policies (a refused policy answers `403` first, and nothing is locked) and before any write, the package locks the synced parents and reads, with a locking read through the related resource's `perimeterQuery()`, one child that each list neither keeps nor removes: when there is one, the request answers `409`, writes nothing, and lists every sync in conflict: `{"message": "The relation has a related row that the sync neither lists nor removes. Read the relation again, without include limits or filters, then send the request again.", "conflicts": [{"reason": "unlisted", "path": "mutate.0.relations.lines.0", "key": 106}]}` (`path` names the sync entry, `key` a child that it leaves out). Rows outside the perimeter or hidden by a global scope (trashed rows included) do not count. The usual cause is a list read through an include that stopped at its limit (`@more`), a filtered include, or a child that another request added since the read: read the relation again without include limits or filters, then send the request again. The check holds when it runs: syncs of the same parent wait for each other on the parent lock, but another writer can still add a child after the check and commit, notably on PostgreSQL and on a `MorphMany`, whose morph columns have no foreign key; index the foreign key column of a synced relation. A deadlock retry runs the check again on fresh reads. A custom relation that lists `MutationOperation::Sync` in `mutationOperations()` keeps the sync that links the rows it names and checks nothing; it cannot declare `syncable()` (`InvalidRelationException`), nor can `onlyOperations()` or `withoutOperations()` name `sync` on a has-many relation.
- `sync` and `toggle` compare their keys with the attached rows that the related resource's `perimeterQuery()` returns (one query per parent row and relation); other attached rows stay. `update` reads the same way whether the rows it names are attached (only those rows, none under a created parent): it attaches the others and rewrites the pivot of the attached ones when it sends `pivot`. An `attach`/`detach`/`sync`/`toggle` target without attributes is saved only when its own keys change (a moved child, with its model events), and runs its resource hooks only when its own `relations` change.
- `pivot` goes through the pivot fields of the relation (see Relationships): `create` and `attach` add a pivot row and accept the creatable pivot fields with `creationRules()`; `update`, `sync` and `toggle` accept the updatable pivot fields with `updateRules()` (`MutationOperation::pivotWriteOperation()` maps the operation); `detach` refuses `pivot`. `attach` of a row that the parent already holds, or that an earlier entry attaches, is a `422` under `{entry}.key` (`The mutate.0.relations.tags.0.key field holds a record that is already attached. Use update or sync to change its pivot.`) unless an earlier entry detaches it (one query per parent row and relation). `readonly()` pivot fields are refused, `writeOnly()` ones accepted. Each value is validated by its field (type base rule, `nullable`, `rules()`), with errors under `{entry}.pivot.{key}`, also when `pivot` is missing; another key is a `422` under `{entry}.pivot` (`The created_at key of the mutate.0.relations.tags.0.pivot field is not writable on create.`). A rule naming another pivot field compares with the pivot of the same entry, and a `Rule::when()` condition in a pivot field rule reads the pivot of its entry. Only the writable pivot keys reach the database. Limit: a row that `update`, `sync` or `toggle` adds gets the update rules, since validation runs before the database is read; use `attach` to add a row with the creation rules.
- A custom relation lists its operations as `MutationOperation` cases in `mutationOperations()`. `delete` is not read from that list: a child relation accepts it with `deletable()` only. A relation that returns `[]` takes no entry: `mutate` refuses it (`The mutate.0.relations.replies field is prohibited.`), as on `HasOneThrough` and `HasManyThrough`, and the OpenAPI document leaves it out of `relations`. The package writes each relation by the class of its Eloquent relation; a relation has no write hook: change a write in the resource's `mutating()` and `mutated()`. The pivot rows of a many-to-many relation are written before `mutated()` runs: to write them another way, declare a pivot persister on the relation with `persistedBy()` (see Relationships).
- `withoutOperations(MutationOperation ...$operations)` withdraws operations from any relation (`HasMany::make('lines', LineResource::class)->withoutOperations(MutationOperation::Detach)`), and `onlyOperations(MutationOperation ...$operations)` keeps only the listed ones (`->onlyOperations(MutationOperation::Attach, MutationOperation::Sync)`). The relation accepts the operations of `onlyOperations()` (all its operations without it) less those of `withoutOperations()`, whatever the order of the calls; several `onlyOperations()` calls keep the operations that every call lists, several `withoutOperations()` calls add up, and `onlyOperations()` without operations makes the relation read-only (`mutate` refuses it: `The mutate.0.relations.labels field is prohibited.`; `delete` stays on `deletable()`). An entry with an operation that the relation does not accept is a `422` (`The selected mutate.0.relations.lines.0.operation is invalid.`), and `/details` and the OpenAPI document leave it out. A relation whose `update` is left out by `onlyOperations()` or `withoutOperations()` writes its related rows on `create` only: `attributes` on `sync` and `toggle` and `relations` on `attach`, `detach`, `sync` and `toggle` are a `422` (`The mutate.0.relations.labels.0.attributes field is prohibited when mutate.0.relations.labels.0.operation is sync.`), and the OpenAPI document leaves them out of these entries. It still writes the pivot: an `attach` with `pivot` adds pivot rows, and a `sync` with `pivot` rewrites the pivot columns of the rows it names. A custom relation whose `mutationOperations()` never listed `update` writes its related rows as before. A many-to-many relation that does not accept `detach`, by either method, keeps its rows attached: it does not accept `toggle` either, and `sync` needs `"without_detaching": true`, else a `422` on `without_detaching` (`The mutate.0.relations.tags.0.without_detaching field must be true: this relation does not detach rows.`). The operations belong to the relation, not to the link: the inverse relation (`Tag::tickets` for `Ticket::tags`) still detaches rows unless it limits its operations too. Listing `delete` (a child relation accepts it with `deletable()` only) or an operation that the relation does not accept in either method throws `Lomkit\Rest\Exceptions\InvalidRelationException` when the relation is declared. A custom relation whose `mutationOperations()` leaves out `detach` keeps its `sync` and `toggle` as they are. Withdraw `detach` from a relation whose foreign key or morph type does not accept null: `rest:resource --generate` does it, and `rest:check` reports such a relation that keeps it.

**Response**: one entry per entry of `mutate`, at the same index — for the payload above, `{"operations": [{"operation": "create", "key": 72979, "relations": {"posts": [{"operation": "create", "key": 312}, {"operation": "attach", "key": 42}]}}, {"operation": "update", "key": 7, "relations": {"roles": [{"operation": "sync", "key": [1, 2, 3]}]}}]}`. Each entry has `operation`, `key` (the new key on `create`, the sent key or key list otherwise) and, when it sent `relations`, `relations` keyed as sent (an object for a to-one relation, a list for a to-many one); an entry of a `MorphTo` adds `type`, the morph type of its row. `sync` and `toggle` answer the keys they sent, not the rows they detach or attach besides; `delete` answers the keys it deleted, as sent; under a key list an entry runs once per key and answers once, a `create` with the list of its keys. To get the rows back, send `response`:

**Records**: `"response": {"selects": [{"field": "name"}], "includes": [{"relation": "posts"}], "gates": ["update"]}` next to `mutate` (it also takes `aggregates`) adds `records` after `operations`: one row per entry of `mutate`, at the same index, rendered like a search row (custom `map()` included), read after the transaction through the resource's `perimeterQuery()` — `null` when the row is out of it or gone. Nested entries get no record: reach their rows through `includes`, which keep their `limit` per record. Other search keys (`filters`, `sorts`, `limit`, `text`, ...) and the `create` gate are a `422` (`The response.filters field is prohibited.`). The block needs `viewAny` on the resource and on every resource its includes and aggregates reach, checked before any write (`403`, nothing written), and its gates answer for the rows as written.

**Versioned rows**: a resource that returns `true` from `versioned()` (default `false`) and declares `DateTime::make('updated_at')->readonly()` lets each `update` entry send `version`, the `updated_at` the client read (`"version": "2026-12-01T10:00:00.000000Z"`). In the transaction, after the row checks (`403` first) and before `mutatingAll()`, one `SELECT ... FOR UPDATE` per resource reads the rows again (on every deadlock retry); when a row has another version the request answers `409` and writes nothing, no hook, no `ResourceMutated`: `{"message": "The record was changed by another request.", "conflicts": [{"reason": "version", "path": "mutate.1", "key": 7, "version": "2026-12-01T10:04:12.000000Z"}]}` (`version` is `null` when the row is gone; `reason` tells a version conflict from a sync conflict). `version` is optional; entries under `relations` of a versioned resource accept it too, and a key list needs every row at that version. `version` on a resource that is not versioned, or on an operation other than `update`, is a `422`. `versioned()` without model timestamps or without the `updated_at` field throws `InvalidResourceException`. Use `$table->timestamps(precision: 6)`, since seconds cannot tell two saves of the same second apart, and read the new version back with `"response": {"selects": [{"field": "updated_at"}]}`. `destroy`, `restore` and `forceDestroy` take no version.

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

    // Optional. A confirmation that the endpoint checks: return one and the first request answers 428
    // with a signed token; the same body sent again with that token runs the action. null runs at once.
    // public function confirmation(\Lomkit\Rest\Actions\ActionFields $fields, int $rows): ?\Lomkit\Rest\Confirmation\Confirmation
    // { return $rows < 50 ? null : \Lomkit\Rest\Confirmation\Confirmation::make("Notify {$rows} users?")->data(['rows' => $rows]); }

    // Optional. A policy ability checked on every row before the action runs (403 on a refused row).
    // public function authorizeEach(): ?string { return 'update'; }

    // Optional. Transactions: Chunk (default, one per chunk, also around handle() in the worker),
    // Whole (all chunks in one, synchronous actions only, also ->atomic()), Row (one per row, queued too,
    // also ->transactionPerRow(); rows reach handleRow() below, not handle()), None (also ->withoutTransaction()).
    // public \Lomkit\Rest\Actions\ActionTransaction $transaction = \Lomkit\Rest\Actions\ActionTransaction::Whole;

    // Optional. Route middleware of this action, after the resource and operate middleware.
    // public array $middleware = ['throttle:exports'];
    // public array $withoutMiddleware = ['auth:sanctum'];

    public function handle(\Lomkit\Rest\Actions\ActionFields $fields, \Illuminate\Support\Collection $models): void
    {
        // $fields->expires_at is a CarbonImmutable; $fields->notify is a bool, true when not sent
        foreach ($models as $model) { /* ... */ }
    }

    // With ActionTransaction::Row, instead of handle(): beforeRow() runs first, outside any transaction
    // (an external call, not run again on a deadlock), then handleRow() inside the transaction of the row.
    // public function beforeRow(\Lomkit\Rest\Actions\ActionFields $fields, \Illuminate\Database\Eloquent\Model $row): void {}
    // public function handleRow(\Lomkit\Rest\Actions\ActionFields $fields, \Illuminate\Database\Eloquent\Model $row): void {}

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

Register on the Resource, by its class:

```php
public function actions(): array
{
    return [
        SendWelcomeNotificationAction::class,
    ];
}
```

**List an action by its class.** The action carries its own configuration in its properties (`public ?string $name = 'Send welcome';`, `public array $middleware = ['throttle:mails'];`, `public bool $destructive = true;`, `public ActionTarget $target = ActionTarget::None;`), and the container builds it only when it is used: an `operate` request for its key, `/details`, the OpenAPI document, `rest:check` or a queued run. A request for another action never builds it, and its constructor can inject services. When the routes register, the route key comes from the default value of `$uriKey`, else of `$name`, else from the class name, and the middleware from the defaults of `$middleware` and `$withoutMiddleware`, all read without building the class. `$uriKey` sets the key apart from the name: `public ?string $uriKey = 'end';` on `EndAttachmentAction` gives `/actions/end`, and `/details` keeps the name `End Attachment`. `InvalidActionException` refuses, when the routes register, a class that overrides `name()` or `uriKey()` (set `$uriKey` and `$name`, or list it as an instance), `Action::class` itself, a class that does not extend `Action`, and an abstract class. A built action whose key, `$middleware` or `$withoutMiddleware` differ from these defaults, such as a constructor that calls `$this->middleware()` or a promoted constructor parameter `$middleware` or `$uriKey`, throws `InvalidActionException` and does not run; so does a container binding that returns another class, with a message that names the binding. The other declaration errors of a class entry (`InvalidActionException`, `InvalidFieldException`, a constructor parameter that the container cannot resolve) throw when it is first built, not when the routes register: `rest:check` builds every action and metric and reports these errors, so run it in CI. Each action route also records the key and middleware it was registered with, and survives `route:cache`: an action, listed by class or as an instance, whose key or middleware differ when a request or a queued run builds it throws `InvalidActionException` and does not run, so run `php artisan route:cache` again after you change them. An instance still works, for an action reused with other settings in several resources: `ExportTickets::make()->middleware('throttle:exports')->meta(['color' => '#FFFFFF'])` (`meta()` merges; `getMeta()` reads). `make()` calls the constructor with the arguments it receives, not through the container.

Call:

```
POST /api/users/actions/send-welcome-notification
{
  "fields": [{"name": "expires_at", "value": "2026-04-29"}],
  "search": {"filters": [{"field": "has_received_welcome", "value": false}]}
}
```

Note the **`fields` payload is an array of `{name, value}` pairs**, not an object, and each name may appear only once (a repeated name is a `422` under `fields.{index}.name`). An entry accepts `name` and `value` only: another key is a `422` (`The fields.0.default field is prohibited.`). The list takes at most `rest.limits.sizes.fields` entries (default `100`), as do the `fields` of an instruction. The `search` block resolves the target models (the whole `search` schema without `page`, which is a `422`; `text` only on a resource that declares `text()`, and the action then acts on the matching rows only, while on other resources `text` is a `422` "The search.text field is prohibited."; with `ScoutEngine`, see "Search engines" for how the keys are read and capped). Actions with the `Search` target require `search`, an object: `"search": {}` runs the action on every model of the perimeter, and a body without `search` is a `422` under `search` ("The search field is required."). Actions with the `None` target omit `search` and receive an empty collection.

The target of an action (`Lomkit\Rest\Actions\ActionTarget`, the `$target` property or `->target()` when declaring) decides what the request body may carry:

| Target | `search` | `resources` | Models impacted |
|---|---|---|---|
| `ActionTarget::Search` (default) | required | prohibited | whatever `search` resolves — every model with `"search": {}`, at most `$maxResources` |
| `ActionTarget::Resources` | prohibited | required, list of ids | exactly the ids named, each returned by the resource's `perimeterQuery()` |
| `ActionTarget::None` | prohibited | prohibited | none, `handle()` gets an empty collection |

Reach for `ActionTarget::Resources` when an action must never run on every model: the caller has
to name its targets, and omitting `resources` is a `422`. Ids that do
not exist, or that `perimeterQuery()` hides, are a `422` under `resources.{index}`, checked with one query
per 1,000 ids. Keys are typed like the resource's `ID` field, and repeated ids are a `422`.
`resources` is capped at
`$maxResources` ids per request (default 1000) — lower it per action when the blast radius should be
smaller, raise it, or set it to `null` to remove the cap on every target.

```jsonc
// POST /api/users/actions/deactivate-users
{ "resources": [1, 5, 9], "fields": [{ "name": "reason", "value": "spam" }] }
```

`details` (with `with=actions`) gives each action's target as `"target": "search"`, `"resources"` or `"none"`, and `destructive` and `confirm` (set with `->destructive()` and `->confirm('...')` when declaring, or as properties; `false` and `null` by default; hints for clients only), `confirmation` (`true` when the action declares `confirmation()`), and with `actions.authorized`, `authorized` (whether the user passes the abilities of its route).

**Fields are typed `Lomkit\Rest\Fields\*` objects.** Each field is validated with its type rules, `nullable()` and `rules()` as a normal Laravel validation: presence and cross-field rules (`required`, `required_if:status,published`, `present`, …) fire even when a field is absent from the `fields` array. Validation errors are keyed by field name — `fields.{name}` (e.g. `fields.expires_at`) — while an unknown or missing field name is reported positionally as `fields.{index}.name`. An `Enum` field must name its enum class (`Enum::make('status', PostStatus::class)`), because action fields are not bound to a model. Field names must not be numeric: `Integer::make('2')` throws `InvalidFieldException`. The request's `messages()` and `attributes()` apply to the `fields.{name}` keys, and so do translation lines keyed by the full key (`validation.custom.fields.{name}.{rule}`); `validation.attributes.{name}` still names the field, unless the field declares `label()`.

**Field rules run in the request's validator.** A rule that names another input compares with that input: the field parameters of Laravel's dependent rules (`required_if`, `gte`, `after`, `same`, …), `date_equals` and `in_array`, as strings or arrays, in `Rule::date()` / `Rule::numeric()` / `Rule::string()` / `Rule::array()` objects and inside `Rule::when()`. Other rule objects see the whole request: a `DataAwareRule` receives the whole payload, with each input value under `fields.{name}`, and rule objects and closures receive the full key (`fields.{name}`, `search.instructions.{index}.fields.{name}`) as their attribute.

**`handle()` receives `Lomkit\Rest\Actions\ActionFields`**: each declared input is a property cast to its field type (`Date`/`DateTime` → `CarbonImmutable`, `Integer` → `int`, `Boolean` → `bool`, `Enum` → the enum case), with `default()` values filled in; an input not sent and without default reads `null`. `has('name')` (sent, `null` included, or defaulted) and `filled('name')` (`has()` and not `null`, also `isset($fields->name)`) tell them apart; `only('a', 'b')` and `toArray()` return the inputs `has()` reports, in `fields()` order; `user()` is the user who sent the request (also in the worker) and `target()` the `ActionTarget`. Typed readers return the cast value or `null`: `string()`, `integer()` (also from a string that is exactly an integer: `"3"`, not `"3.5"`), `float()` (also from an `int` or a numeric string, so a `Number` sent as `"19.99"` reads `19.99` while `$fields->price` stays the string), `boolean()`, `date()` (`?CarbonImmutable`), `enum('status', Status::class)` (`?Status`) and `array()`, as in `$fields->date('starts_on')`; the others convert nothing, and a value of another type (a case of another enum included) throws `InvalidFieldException`. With `required: true` (`$fields->date('starts_on', required: true)`, `$fields->enum('status', Status::class, required: true)`) a reader returns the non-null type for PHPStan and throws `InvalidFieldException` on a `null` value, so give the input the `required` rule. A name that `fields()` does not declare throws `InvalidFieldException`. PHPStan reads the inputs as properties through the package's `extension.neon` (included by `phpstan/extension-installer`, or add `vendor/lomkit/laravel-rest-api/extension.neon` to the `includes` of `phpstan.neon`). The object is read only and serializes (values, target, a reference to the user). An entry sent without `value` counts as absent.

**`$request->payload()` returns the parsed `Lomkit\Rest\Actions\OperationNode`** once the request passed validation, in the resource's `operating()` event for example (through `$this->request()`). The node has `action`, `fields` (the input values) and `target`: the parsed search of the `Search` target (a `SearchNode`), the keys of the `Resources` target (a `KeyList`), or `null` for the `None` target. `$request->payload()->keys()` returns the keys of the `Resources` target. The action's `fields()` runs once per request.

**Response**: `{"data": {"impacted": 150, "result": null}}`. `impacted` is the number of rows given to `handle()`, or put into jobs for a queued action, never the search `limit`. `result` is what `respond(ActionFields $fields, int $impacted): ?ActionResponse` returns, run once after the last chunk (or after the jobs or the batch are sent): `ActionResponse::message($text)` → `{"type": "message", "message": ...}`, `danger($text)` → `{"type": "danger", "message": ...}`, `download($url, $name)` → `{"type": "download", "url": ..., "name": ...}`, `redirect($url)` → `{"type": "redirect", "url": ...}`, `created($models)` → `{"type": "created", "keys": [...]}`, `data([...])` → `{"type": "data", "data": {...}}`; `->withData([...])` on any type adds `"data": {...}` (an array with string keys, `{}` when empty; a list throws `InvalidActionException`); status 200.

**Chunks.** Rows reach `handle()` (or the jobs) in chunks of `$chunkCount`. A search without `sorts` and `limit` is read with `chunkById()` (ascending key), so an action that changes a filtered column still reaches every row; with `sorts` or `limit` the keys are read first in the search order and each chunk of keys is loaded through the same search; the `Resources` target loads `resources` in request order. Every target is capped by `$maxResources` (a count first, or at most `$maxResources` + 1 keys; `422` under `search`). Each chunk runs in a transaction on the resource model's connection, a queued job around `handle()` in the worker (`ActionTransaction::Chunk`); `->atomic()` (`ActionTransaction::Whole`) runs all chunks in one (a queued action with it throws `InvalidActionException`); `->transactionPerRow()` (`ActionTransaction::Row`) gives each row its own transaction, in the request and in the worker, whatever `$chunkCount`: the rows reach `handleRow(ActionFields $fields, Model $row): void` inside it instead of `handle()`, after `beforeRow(ActionFields $fields, Model $row): void` outside any transaction; a throw rolls back its row and stops the run (or the job), the rows before it stay written. `Row` without `handleRow()`, with `handle()` or on the `None` target, and `handleRow()` or `beforeRow()` without `Row`, throw `InvalidActionException`. `->withoutTransaction()` (`ActionTransaction::None`) opens none, so rows written before a throw stay written.

**Model type.** `Action` declares `@template TModel of Model = Model`: `handle()` receives a `Collection<array-key, TModel>`, and `beforeRow()` and `handleRow()` a `TModel`. An action that reads its own model declares it with `/** @extends Action<Invoice> */` above the class and narrows the PHPDoc of its parameters (`@param Collection<array-key, Invoice> $models`, `@param Invoice $row`). Without `@extends`, the model is `Model` and PHPStan reports nothing new.

**Abilities.** Before validation, `operate` resolves the action: an unknown action is a `404` for a user allowed `viewAny` on the resource and a `403` for the others. A known action checks, in this order, `viewAny` on the resource, the abilities the resource's `abilities()` returns for `Endpoint::Operate` (none by default), then those `Action::abilities(): array` returns (`['viewAny']` by default, for every action, whatever its target). Both lists add to `viewAny`: an action returning `['export']` checks `viewAny`, then `export`. An action returning `[]` runs with none of these checks (no `viewAny`, no `Endpoint::Operate` abilities, and its search does not check `viewAny` on the resource): use it for an action any user may run, guests included. Includes, aggregates and filters on relations in the action's `search` still need `viewAny` on the related resource, as in a search.

**Routes and middleware.** `Rest::resource()` reads `actions()` when it registers the routes (at boot or during `route:cache`, without a user) and gives each action `POST /{resource}/actions/{uriKey}`, named `{name}.operate.{uriKey}`; `POST /{resource}/actions/{action}` comes last and answers unknown keys (`403` without `viewAny`, else `404`), also for an action that `actions()` returned only after the routes were registered. Keep `actions()` the same for every user and free of queries; run `route:cache` again after adding an action. `Rest::resource()` builds the resource through the container and reads `actions()` and `metrics()`: a class entry is not built then, while an instance entry is created by `actions()` or `metrics()`, so its constructor runs at boot, in every Artisan command and on every request without `route:cache`. List an action or metric that injects services by its class; an instance created with `X::make()` gets only the arguments it receives, so inject nothing in it that needs a user, a tenant, the database or keys, and resolve that in `handle()`. `instructions()` is read when a search or `/details` first needs it, not when the routes register. `->middleware('throttle:exports')` and `->withoutMiddleware('auth:sanctum')` when declaring (or the `$middleware` and `$withoutMiddleware` properties) add to the package, resource and `middlewareFor('operate')` middleware and can remove any of them, group middleware included; name a removed middleware as declared, with its parameters. Two `throttle` middleware without a limiter name on one route count each request twice (they share the user's key): give the action a named limiter. Two actions with one `uriKey()` throw `InvalidResourceRouteException` when the routes register. The progress route of batches stays `GET /{resource}/actions/{action}/batches/{batch}`, without the action's middleware. `rest:check` reports an action route that removes the authentication middleware. In tests, put Laravel's `Illuminate\Foundation\Testing\WithCachedRoutes` on the base test case: the routes of the first test are compiled in memory and reused by the following tests, so `Rest::resource()` and the resources it builds no longer run between tests; a test that needs other routes extends a base without the trait.

**Row checks.** `authorizeEach(): ?string` (null by default) names a policy ability checked on every row of the `Search` or `Resources` target, through the resource's `authorizedTo()` (memo, `authorizationCache()`: one store read and one write per chunk). By default all chunks are read and checked before any `handle()`, job or batch, then read again to run on the checked rows only (a row added in between is not run; the checked rows count for `confirmation()` and for `$maxResources`: the checks stop with the `422` at the first chunk that passes it): a refused row is a `403` (status and message of the first refusal; `AuthorizationDenied` holds every refusal, at most 50, under `resources.{index}` or `search`). With `->skipUnauthorized()` (or `public bool $skipUnauthorized = true`) each chunk is filtered just before it is handed and `impacted` counts the allowed rows. The worker of a queued action does not check again. `skipUnauthorized()` without an ability, or an ability on the `None` target, throws `InvalidActionException` when the actions are read. `rest:check` reports an action on the `Search` or `Resources` target without an ability (return `'view'` for an action that only reads).

**Confirmation.** `confirmation(ActionFields $fields, int $rows): ?Confirmation` (`Lomkit\Rest\Confirmation\Confirmation`, `null` by default) makes the endpoint check that the user confirmed the run. `$rows` is the number of rows the action would run on: the rows the search matches in the perimeter, capped by `$maxResources`, the keys of the `Resources` target, `0` for the `None` target, counted before `skipUnauthorized()` filters (`impacted` can end lower). Return `null` to run at once (below a threshold, for example), or `Confirmation::make('Close 37 tickets?')->data(['rows' => 37])` (string keys, `{}` when empty; a list throws `InvalidConfirmationException`), `->expiresIn(600)` (seconds, 300 by default). The request then answers `428` after validation (`422`) and the entry and row checks (`403`), and runs nothing: no `operating()`, no `handle()`, no job, no batch, no event:

```json
{"message": "Close 37 tickets?", "confirmation": {"token": "1796119500.9c2f…", "expires_at": "2026-12-01T10:05:00Z", "data": {"rows": 37}}}
```

The client sends the same body again with `"confirmation": "<token>"` (a string; another type is a `422`) and the action runs. The token is signed with `app.key` (an empty key throws `InvalidConfigException` once a confirmation is due) and holds no state: it is valid for the same user and guard, resource, action, `search`, `resources` and `fields` (key order and unknown first-level keys do not count) and number of rows, until it expires, and can be used again until then. Any other token, or a count that changed, answers a new `428` with a fresh token. Precognition stops before the confirmation. `confirm()` and `destructive()` stay hints for clients. The writes of a resource ask the same way, see [Confirmed writes](#confirmed-writes).

**Queuing**: implement `Illuminate\Contracts\Queue\ShouldQueue` to dispatch one job per chunk of `$chunkCount` models, at most `rest.limits.sizes.jobs` jobs per run (default `100`, at least `1`): with more rows, each job takes the rows divided by that limit, rounded up (250 rows, `$chunkCount` 2: 84 jobs of 3 rows). Combine with `Lomkit\Rest\Contracts\BatchableAction` and use `withBatch(ActionFields $fields, PendingBatch $batch): void` to register `then`/`catch`/`finally` callbacks. Route the jobs with Laravel's queue attributes on the action (`#[Queue('exports')]`, `#[Connection('redis')]`, `#[Tries(3)]`, `#[Timeout]`, `#[Backoff]`, `#[Delay]`, `#[MaxExceptions]`, `#[FailOnTimeout]`) or `Queue::route(ExportOrders::class, 'exports')`; an action has no `$queue`/`$connection` properties and no `dispatch()`. The job (`Lomkit\Rest\Actions\QueuedAction`) holds the resource and action classes, the action key, the input values as sent, the row keys and a reference to the user; the worker rebuilds the context for that user (`$this->request()->user()`, policies and `perimeterQuery()` see them; `auth()->user()` does not), casts the input values again, and reloads the rows by key through `perimeterQuery()` (not for an engine text search) with the global scopes and `search.trashed`, without the search's includes. Rows gone or out of the perimeter are skipped; a missing user or a removed action fails the job with `QueuedActionException`. The worker reads the database connections current when the job runs: with several tenants, initialise the tenant in the worker before the job (stancl/tenancy's `QueueTenancyBootstrapper` does it from the payload), or the job fails on the central connection (database per tenant) or loads its rows by key across tenants (single database). `release()`, `attempts()` and `batch()` work in `handle()`. A `BatchableAction` answers `"batch": "<id>"`; its jobs skip once the batch is cancelled (`SkipIfBatchCancelled`). `Rest::resource(...)->withBatchProgress()` adds `GET /{resource}/actions/{action}/batches/{batch}` (`{name}.batch`): the progress (`id`, `total_jobs`, `pending_jobs`, `failed_jobs`, `progress`, `finished`, `cancelled`) for the user who started the batch, `404` for anyone else and for a guest's batch.

## Recipe: exporting rows

The package has no export endpoint: a queued, batched action covers it. The client sends the columns it wants and the search of its list, each job writes one chunk of rows to a CSV part, and the batch joins the parts into one file. A small export can stream in the request instead, with `lazy()` (see "Searching outside HTTP").

```php
<?php

namespace App\Rest\Actions;

use App\Models\Ticket;
use Illuminate\Bus\PendingBatch;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Actions\ActionFields;
use Lomkit\Rest\Actions\ActionResponse;
use Lomkit\Rest\Contracts\BatchableAction;
use Lomkit\Rest\Fields\Json;

class ExportTickets extends Action implements BatchableAction, ShouldQueue
{
    public const array COLUMNS = ['id', 'title', 'status'];

    public int $chunkCount = 500;

    public ?int $maxResources = 50000;

    private string $export = '';

    #[\Override]
    public function fields(): array
    {
        return [Json::make('columns')->rules(['required', 'list', Rule::in(self::COLUMNS)])];
    }

    #[\Override]
    public function withBatch(ActionFields $fields, PendingBatch $batch): void
    {
        $export = $this->export = (string) Str::uuid();
        $columns = $fields->columns;

        $batch->withOption('export', $export)->then(static function () use ($export, $columns): void {
            $disk = Storage::disk('exports');
            $csv = fopen('php://temp', 'w+');
            fputcsv($csv, $columns, escape: '');
            $parts = $disk->files($export);
            sort($parts, SORT_NATURAL);

            foreach ($parts as $part) {
                stream_copy_to_stream($disk->readStream($part), $csv);
            }

            rewind($csv);
            $disk->writeStream("{$export}.csv", $csv);
            $disk->deleteDirectory($export);
        });
    }

    /** @param Collection<int, Ticket> $models */
    #[\Override]
    public function handle(ActionFields $fields, Collection $models): void
    {
        $csv = fopen('php://temp', 'w+');

        foreach ($models as $ticket) {
            fputcsv($csv, $ticket->only($fields->columns), escape: '');
        }

        rewind($csv);
        Storage::disk('exports')->writeStream($this->batch()?->options['export'].'/'.$models->first()?->getKey().'.csv', $csv);
    }

    #[\Override]
    public function respond(ActionFields $fields, int $impacted): ActionResponse
    {
        if ($impacted === 0) {
            return ActionResponse::message('No rows to export.');
        }

        return ActionResponse::download(Storage::disk('exports')->temporaryUrl("{$this->export}.csv", now()->addDay()), 'tickets.csv');
    }
}
```

Declare it in the resource's `actions()` (`ExportTickets::class`) and add the progress route: `Rest::resource('tickets', TicketsController::class)->withBatchProgress()`.

```
POST /api/tickets/actions/export-tickets
{
  "fields": [{"name": "columns", "value": ["title", "id"]}],
  "search": {"filters": [{"field": "status", "value": "open"}]}
}
```

```json
{"data": {"impacted": 5, "batch": "9f0c4d2e-5a1b-4c3d-8e7f-0a1b2c3d4e5f", "result": {"type": "download", "url": "https://files.example.com/3e1f6a2b-7c4d-4e5f-9a0b-1c2d3e4f5a6b.csv?expires=1796205600&signature=…", "name": "tickets.csv"}}}
```

```csv
title,id
"Printer jam, floor 2",8
"Password reset",9
"Monitor flicker",12
"Badge reader",13
"Desk phone",14
```

- **Rows.** `handle()` receives each chunk of the rows that the search matches inside the resource's `perimeterQuery()`: the worker loads them again by key, for the user who sent the request. A chunk holds `$chunkCount` rows, or more when the rows need more than `rest.limits.sizes.jobs` jobs (`100`). `$maxResources` caps the rows of one export (more is a `422`).
- **Columns.** `columns` is a `Json` field: `list` and `Rule::in()` accept only a list of the allowed names, anything else is a `422` under `fields.columns`. `only()` returns the values in the order the client gave.
- **Parts.** The jobs of a batch can run at the same time, so each job writes its own part, named after the first key of its chunk, and the batch's `then()` callback joins the parts in that order under a header row, once every job succeeded. Without `sorts`, chunks follow the key, so the file lists the rows by key.
- **Download.** `respond()` runs once the jobs are sent, before they run: the URL works once the batch is finished, which the client reads at `GET /api/tickets/actions/export-tickets/batches/{batch}`. A search that matches no row sends no job and writes no file, so the action answers a message.
- **Disk.** `exports` is a disk of `config/filesystems.php` that every worker reaches and that builds temporary URLs (`s3`, or `local` with `'serve' => true`).
- **Labels.** To name the columns from the resource's field labels, build the header in `withBatch()` with `$this->resource->definition()->fields()->get($column)->getLabel() ?? $column` for each column.
- **XLSX.** Keep the CSV parts and write the joined file in `then()` with any spreadsheet writer library; the rest stays the same.

## Metrics

A metric computes one result over the rows of a search: a count, a total, a breakdown. Declare it in `metrics()` on the resource, by its class (`TicketBacklog::class`) or as an instance (`TicketBacklog::make()->cacheFor(600)`), with no request parameter (read it with `$this->request()`); each metric gets `POST /{resource}/metrics/{uriKey}`.

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
- The search runs the resource's `searching()` event first, as a search does. A metric that must skip it declares `public bool $withoutSearching = true;` in its class, or `TicketBacklog::make()->withoutSearching()` in `metrics()`. The perimeter, `viewAny` and the validation still apply.
- Abilities: `viewAny`, then the resource's `abilities(Endpoint::Metric)`, then the metric's `abilities()` (`['viewAny']` by default). `[]` runs without these checks, for example for guests with `->withoutMiddleware('auth')`. Relation filters still need `viewAny` on the related resource.
- Routes: named `{name}.metric.{uriKey}`; `POST /{resource}/metrics/{metric}` comes last and answers unknown keys (`403` without `viewAny`, else `404`). `->middleware('throttle:reports')` and `->withoutMiddleware('auth:sanctum')` (or the `$middleware` and `$withoutMiddleware` properties) work as for actions. `Rest::resource()` reads `metrics()` when it registers the routes: keep it the same for every user and free of queries; two metrics with one `uriKey()` throw. A metric listed by its class follows the rules of an action listed by its class: its key and middleware come from the defaults of `$uriKey` (else `$name`), `$middleware` and `$withoutMiddleware` (overriding `name()` or `uriKey()` throws `InvalidMetricException` when the routes register: set `$uriKey` for a key apart from the name), the container builds it for a request with its key, `/details`, the OpenAPI document or `rest:check`, a built metric whose key or middleware differ throws, and its other declaration errors throw when it is first built.
- Details: `with=metrics` lists `{"name", "uriKey", "type", "ranges"}` for each metric, `metrics.meta` adds `meta` and `metrics.authorized` adds `authorized`. `$resource->definition()->metrics()` and `metric($key)` read them.
- OpenAPI: `schema()` returns the Scramble type of the result (`?Type`, `null` by default: the document gives the `type` alone). The built-in types return `Lomkit\Rest\Metrics\ResultSchemas::value()`, `trend()`, `partition()`, `table()` or `progress()`; `trend($this->groupLimit())` and `partition($this->groupLimit())` give `splits` and `parts` their `maxItems`; a metric that changes the result of a built-in type overrides `schema()` too. Keep the return type `?Type`: a narrower one stops the class from loading without Scramble.

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

- `Value` (type `value`): `count($query, $dateColumn = 'created_at')`, `sum($query, $column, $dateColumn = 'created_at')`, `average()`, `min()`, `max()` and `median()` with the parameters of `sum()`, `percentile($query, $column, $percentile, $dateColumn = 'created_at')`. With ranges, `{"value": <the last N days>, "previous": <the N days before>}`; without, `{"value": <every row>, "previous": null}` and the date column is not read. A count without ranges is the number of rows of the search.
- `Partition` (type `partition`): `count($query, $groupBy, $dateColumn = null)`, `sum($query, $column, $groupBy, $dateColumn = null)`, `average()`, `min()`, `max()` and `median()` with the parameters of `sum()`, `percentile($query, $column, $percentile, $groupBy, $dateColumn = null)`. The group column is a `Text`, `Enum`, `Integer` or `Boolean` field. Each part is `{"key", "label", "value"}`: the key is the stored value (the enum value, an integer, a boolean), the label the enum case name or the key as text, the rows without a value form the part `{"key": null, "label": null, ...}`. Parts are sorted by value, largest first, then by key. Without `dateColumn`, a partition reads every row of the search and its parts have no `previous`.
- Previous period: with `dateColumn` (named) and `ranges()`, a partition reads the last N days, as `Value` does, and adds `previous` to each part and to `other`: the same aggregate for the same key over the N days before, in the same query. A key without rows in one of the two periods gets `null` there; parts stay sorted by `value`. Without ranges, it reads every row, `previous` is `null` and the date column is not read. `$this->sum($query, 'amount', 'currency', dateColumn: 'paid_at')` with `{"range": 30}` -> `{"data": {"type": "partition", "parts": [{"key": "EUR", "label": "EUR", "value": 18240.5, "previous": 16002.0}, {"key": "USD", "label": "USD", "value": 9100.0, "previous": 9875.25}], "other": null}}`. A median or a percentile compares the same way, with grouped queries per period: `$this->median($query, 'amount', 'currency', dateColumn: 'paid_at')`.
- Limit: `public ?int $limit = 5;` in the class, or `OrdersByStatus::make()->limit(5)` in `metrics()`, keeps the 5 largest parts and folds the others into `other`: `{"label": "Other", "value": <their count, sum, min, max, average, median or percentile>}`. `other` is null when nothing is folded. Without `limit()`, `rest.limits.sizes.groups` applies (100 by default, at least 1); `limit()` raises or lowers it for one metric. The query sorts the groups and reads one more than the limit; when it folds, a second query computes `other` over the rows outside the kept keys.
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
- Ranges: `ranges()` maps a number of days to a label. The request sends `{"range": 90}` for another range; an undeclared key, or `range` for a metric without ranges, is a `422`. In a custom metric, `$this->range()` gives the selected `MetricRange` (`value`, `label`) or null. `ranges()` keys are integers greater than 0, or the metric throws when it is built.
- Numbers: a count is an integer; a sum, min or max is an integer for an `Integer` field and a float for another `Number` field; an average, a median or a percentile is a float. Over no row: count and sum `0`, average, min, max, median and percentile `null`. Floats keep their fraction in the response (`52.0`).
- Median and percentile: `$this->median($query, 'resolution_minutes', 'closed_at')`; `$this->percentile($query, 'resolution_minutes', 0.9, 'closed_at')` gives the value under which 90% of the values fall, interpolated between the two nearest rows as PostgreSQL `percentile_cont()`. The percentile is a number from 0 to 1, or `InvalidMetricException` when the metric runs. Rows without a value are left out, as for an average. The database sorts the rows of each group: index the column of a large table.
- Fields: the helpers read fields that the resource declares. A sum, average, min, max, median or percentile needs a `Number` field (`Integer` included), the range a `Date` or `DateTime` field; a partition groups by a `Text`, `Enum`, `Integer` or `Boolean` field; any other field throws `InvalidMetricException` when the metric runs.
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

- Helpers: `countByDays($query, $dateColumn = 'created_at', $splitBy = null)`, `countByWeeks()`, `countByMonths()`, and `sumBy...`, `averageBy...`, `minBy...`, `maxBy...`, `medianBy...` with `($query, $column, $dateColumn = 'created_at', $splitBy = null)`, `percentileBy...` with `($query, $column, $percentile, $dateColumn = 'created_at', $splitBy = null)`.
- Ranges: a Trend declares `ranges()`, or it throws when it is built. A key counts periods, the current one included: `6` on a monthly trend is this month and the 5 before. The series starts at the start of the first period and ends now.
- Labels: a day `2026-11-07`, a week the date of its first day (`2026-11-02` for a week from Monday), a month `2026-11`. Every period of the range is in the series, oldest first; a period without rows is `0` for a count or a sum, `null` for an average, a min, a max, a median or a percentile.
- Weeks start on Monday. `public int $weekStartsOn = Carbon::SUNDAY;` in the class, or `RevenuePerWeek::make()->weekStartsOn(Carbon::SUNDAY)` in `metrics()`, starts them on another day (`0` Sunday to `6` Saturday, or the metric throws when it is built).
- Time zone: `{"timezone": "Europe/Paris"}` counts the days, weeks and months in that zone, changes of offset included; without `timezone`, `config('app.timezone')`, the zone in which Laravel stores the dates. An unknown zone or an offset such as `+01:00` is a `422`. A `Date` field is a calendar date: only the range follows the zone. Every metric accepts `timezone`; a custom metric reads it with `$this->timezone()`, and `$this->periods()` uses it.
- Split: `$this->countByMonths($query, 'placed_at', splitBy: 'status')` adds `splits`, one series per value of the column, and `other`: `{"series": [<every row>], "splits": [{"key": "paid", "label": "Paid", "series": [...]}, ..., {"key": null, "label": null, "series": [...]}], "other": null}`. Keys and labels as a partition, sorted by key, the rows without a value last.
- Limit: `public ?int $limit = 5;` in the class, or `OrdersPerMonth::make()->limit(5)` in `metrics()`, keeps the 5 splits with the largest total over the range, chosen as the parts of a partition, and folds the others into `other`: `{"label": "Other", "series": [...]}`. `other` is null when nothing is folded. Without `limit()`, `rest.limits.sizes.groups` applies (100 by default). One query ranks the splits and reads one more than the limit, one reads the series of the kept splits, and one the series of `other` when it folds. `->labels(['paid' => 'Payée'], other: 'Autre')` renames the splits and `other`.
- Fields: the date column is a `Date` or `DateTime` field, the column of a sum, average, min, max, median or percentile a `Number` field, the split column a `Text`, `Enum`, `Integer` or `Boolean` field; any other field throws `InvalidMetricException` when the metric runs.
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

A `Table` gives the top rows of a grouped aggregate: one row per value of a column, one column per count, sum, average, min, max, median or percentile, ranked by one of them.

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

- Helper: `rows($query, $groupBy, $columns, $orderBy = null, $direction = 'desc', $dateColumn = 'created_at')`. `$columns` maps a name (letters, digits and underscores, starting with a lowercase letter) to `TableColumn::count()`, `sum($field)`, `average($field)`, `min($field)`, `max($field)`, `median($field)` or `percentile($field, $percentile)`; `->label('...')` names the column, else the name in words ("Revenue"). A percentile column adds its percentile to its entry in `columns`: `{"name": "p90", "label": "P90", "aggregate": "percentile", "percentile": 0.9}`.
- Order: by `$orderBy`, the first column by default, largest first; `'asc'` for the smallest first. Rows without a value for that column come last; ties are ordered by key, the rows without a key last.
- Limit: `public int $limit = 5;` in the class, or `TopCustomers::make()->limit(5)` in `metrics()`. From 1 to 100, else the metric throws when it is built; 10 by default. The rows after the limit are not returned.
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
- Fields: the group column is a `Text`, `Enum`, `Integer` or `Boolean` field, the column of a sum, average, min, max, median or percentile a `Number` field, the date column a `Date` or `DateTime` field; any other field throws `InvalidMetricException` when the metric runs. Index the group column of a large table: the database groups every row of the search before it keeps the top rows.

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

- `->cacheFor()`, or `public ?int $cacheFor = 600;` in the class, keeps the result for that long; without it nothing is kept and no cache store is called. Less than 1 second throws `InvalidMetricException` when the metric is built.
- Each user gets their own result. `->cacheScope('global')`, or `public CacheScope $cacheScope = CacheScope::Global;` (`Lomkit\Rest\Metrics\CacheScope`), shares one result between the users and guests whose `perimeterQuery()` compiles to the same SQL and bindings, on the resource and on each resource that the `search` reaches through a relation filter or group: the users of one team share it, another team gets its own. Only that SQL is keyed: a perimeter that also depends on something else, such as a global scope of the model or one that it removes for some users, adds that to `authorizationCacheContext()`. With several tenants, put the tenant in it: per-tenant databases compile the same perimeter SQL and a tenant global scope is not keyed, so the tenants would share one global result. Another scope than `'user'` or `'global'` throws `InvalidMetricException`.
- The key holds the user (guard, user class and key, or guest; with `'global'`, the SQL and bindings of `perimeterQuery()` instead, on the resource and on each resource that the `search` reaches), the resource, the metric class and `uriKey()`, the public properties of the metric that hold a number, a string, a boolean, `null` or an enum (`->limit()`, `->weekStartsOn()`, the scope, your own settings), the `range` and `timezone` of the request (the first range and `config('app.timezone')` when it sends none), its `search` (the keys of an object in any order), the values of `authorizationCacheContext()` on the resource and on each resource that the `search` reaches, and the stamps of the decision cache. A metric that reads anything else, such as a header, adds it to `authorizationCacheContext()`.
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

`Instruction` has the same template: with `/** @extends Instruction<Invoice> */` above the class, `handle()` documents `@param Builder<Invoice> $query`. Without `@extends`, the query is a `Builder<Model>`.

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
"instructions": [{"name": "odd-even-id", "fields": [{"name": "type", "value": "odd"}]}]
```

To send one instruction twice, give one entry an `alias` (`{"name": "odd-even-id", "alias": "even_ids", ...}`): each entry of one `instructions` list needs its own key, see **Instruction `alias`** above.

`->meta([...])` (which merges) and `getMeta()` are also available on Instructions. They feed the details entry; `searchMeta(InstructionFields $fields): array` adds an object to the `meta` of the search page instead, under `meta.instructions.{alias or name}`, see "Response" in the `search` payload above.

Instruction `fields()` are typed and validated exactly like Action fields: full Laravel validation (including `required` on absent fields), with errors keyed by name under `search.instructions.{index}.fields.{name}` (custom translation lines go under `validation.custom.search.instructions.*.fields.{name}.{rule}`); a name repeated in `fields` is a `422` under `search.instructions.{index}.fields.{position}.name`. `handle()` and `handleEngine()` receive `Lomkit\Rest\Instructions\InstructionFields`: the reading API of `ActionFields` (properties, typed readers such as `$fields->enum('direction', Direction::class)`, `has()`, `filled()`, `only()`, `toArray()`), without `user()` and `target()`; both extend `Lomkit\Rest\Fields\FieldValues`.

An `afterQuery()` callback that an instruction or a scope of `scopes()` registers receives an `Illuminate\Database\Eloquent\Collection` for each batch of rows the package loads: the page, a batch of an export or of an action, the rows of an include. It never receives keys or counts, and it must not filter or reorder the rows, because totals and chunks are counted in SQL. A callback registered inside `perimeterQuery()` is not kept.

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

Mutation constraints (chainable, may take a closure for conditional logic): `requiredOnCreation()`, `prohibitedOnCreation()`, `requiredOnUpdate()`, `prohibitedOnUpdate()`. The update constraints also apply to the `relations` of an `attach`, `detach`, `sync` or `toggle` entry. `onlyOperations()` and `withoutOperations()` limit the operations of a relation (see the `mutate` payload).

Many-to-many relations (`BelongsToMany`, `MorphToMany`, `MorphedByMany`) declare their pivot columns with typed `Field` objects in `pivotFields([...])` (`withPivotFields()` and `withPivotRules()` are gone; strings and duplicate names throw `InvalidFieldException`). Each pivot field is a column of the pivot table, written as sent (a `Json` pivot field encodes its value when the pivot class writes the column as it is, see `Json` above): a `virtual()` or `inputOnly()` pivot field or one with `fillUsing()` or `resolveUsing()` throws `InvalidFieldException`. `getPivotFields()` returns a `FieldSet` bound to the pivot class of the Eloquent relation (the `using()` class or `Pivot`), so an `Enum` pivot field reads the pivot casts. The pivot fields decide what `pivot` accepts on each operation (see the `mutate` payload) and what the `pivot` of an included row returns: the selectable pivot fields only, never `writeOnly()` or `selectable(false)` ones. Filters on `{relation}.pivot.{key}` accept the filterable pivot fields only (not `writeOnly()`, `filterable(false)`, or `selectable(false)` without `filterable()`), with the operators and values of their type. A custom relation with pivot fields implements `Lomkit\Rest\Contracts\PivotRelation` (the `HasPivotFields` trait does).

A many-to-many relation writes its pivot rows through a pivot persister: `Lomkit\Rest\Mutations\EloquentPivots` by default, which writes the pivot table with the Eloquent relation. `persistedBy(string $persister)` declares a class that implements `Lomkit\Rest\Contracts\PivotPersister` (another class throws `InvalidRelationException` when the relation is declared, and so does a container binding that returns something else); the container resolves it on each write, so a deadlock retry gets a new instance. The package decides what is allowed (validation, every policy and link ability, the link checks of `attach`, the transaction and its retries) and splits each operation into the three calls of the persister, each with a `Lomkit\Rest\Mutations\PivotWrite` that names at least one row, in this order: `detach()`, `attach()`, `updatePivot()`:

| Operation | `detach()` | `attach()` | `updatePivot()` |
| --- | --- | --- | --- |
| `create` | | the created row | |
| `update` | | the named rows not attached | the named rows attached, when `pivot` is sent |
| `attach` | | the named rows | |
| `detach` | the named rows | | |
| `sync` | the attached rows not named (none with `without_detaching`) | the named rows not attached | the named rows attached, when `pivot` is sent |
| `toggle` | the named rows attached | the named rows not attached | |

"Attached" means what the Eloquent relation selects, its pivot conditions included, through the related resource's `perimeterQuery()`, after the earlier entries of the request. `PivotWrite` holds `parent`, `relation` (the Eloquent relation method), `related` (the rows), `pivot` (the storage values of the writable pivot fields, `[]` on `detach`) and `entry` (the `MutationNode`); `user()` returns the user the package checked the policies for, `null` for a guest, as `ActionFields::user()` does; `pivots()` returns a fresh Eloquent relation, `keys()` the keys of the rows and `keyPath()` the error key of the entry (`mutate.0.relations.members.0.key`, the entry path on `create`). A call must be idempotent: a `detach` entry may name a row that is not attached. After `attach()` and `detach()` of a declared persister, the package reads the links through the Eloquent relation (one query per 1000 keys) and throws `Lomkit\Rest\Exceptions\PivotPersisterException` when a row is not linked after `attach()` or still linked after `detach()`; `updatePivot()` is not read back. A persister that keeps withdrawn rows declares the condition on the Eloquent relation (`->wherePivotNull('withdrawn_at')`): without it, a withdrawn row still counts as attached, `attach` refuses it as already attached and the `detach()` check throws. A `ValidationException` thrown by the persister answers a `422` with its keys (use `$write->keyPath()`) and an `AuthorizationException` a `403`; both roll back the request. A deadlock retry runs the persister again: send mails, HTTP calls and jobs after the commit, keep in-memory state replay-safe, open no transaction of your own and swallow no exception.

```php
BelongsToMany::make('members', MemberResource::class)
    ->pivotFields([Text::make('role')->nullable()])
    ->persistedBy(ProjectMemberships::class);

// Project::members(): $this->belongsToMany(Member::class)->withPivot('role')->wherePivotNull('withdrawn_at')
final class ProjectMemberships implements PivotPersister
{
    public function attach(PivotWrite $write): void
    {
        $pivots = $write->pivots();

        foreach ($write->keys() as $key) {
            $pivots
                ->newPivotStatement()
                ->updateOrInsert(
                    [$pivots->getForeignPivotKeyName() => $write->parent->getKey(), $pivots->getRelatedPivotKeyName() => $key],
                    [...$write->pivot, 'withdrawn_at' => null],
                );
        }
    }

    public function detach(PivotWrite $write): void
    {
        $write
            ->pivots()
            ->newPivotStatementForId($write->keys())
            ->update(['withdrawn_at' => now()]);
    }

    public function updatePivot(PivotWrite $write): void
    {
        $write
            ->pivots()
            ->newPivotStatementForId($write->keys())
            ->update($write->pivot);
    }
}
```

## Delete / restore / force

```
DELETE /api/users          body: {"resources": [5, 6]}      // destroy (soft if SoftDeletes is used)
POST   /api/users/restore  body: {"resources": [5, 6]}      // requires withSoftDeletes()
DELETE /api/users/force    body: {"resources": [5, 6]}      // requires withSoftDeletes()
```

All three return the affected records with the selectable fields of their resource: `{"data": [{...}], "meta": {}}`. They take no `search`: an empty array is accepted, and any other `search` (`null` and blank strings included) is a `422` under `search`, so their rows carry no `selects`, gates or aggregates. Each entry of `resources` is a key typed like the resource's `ID` field (an integer for integer keys; a string, UUID or ULID otherwise), so a list or an object in `resources` is a `422` under `resources.{index}`. `resources` must be a list (an object is a `422`, `The resources field must be a list.`) of at most `rest.limits.sizes.resources` keys (default `1000`), and a repeated key is a `422` on every copy (UUID and ULID keys compare without case). During validation, the keys are checked through the resource's `perimeterQuery()`, with the global scopes (trashed rows included on restore and force), with one query per 1,000 keys: a key that it does not return, a trashed key on destroy included, is a `422`, `The selected resources.0 is invalid.` That query also loads the models, with the keys typed by the `ID` field whatever the model's `$keyType`, so `perimeterQuery()` runs once and the models are read before any event of the endpoint. Then every row is checked (`delete`, `restore` or `forceDelete`, through the resource's `authorizedTo()`) before any row is written: a denied row is a `403` whose `AuthorizationDenied` holds every denied row (at most 50, under `resources.{index}`), with the message of the first one in the order of `resources`. The rows are written in one database transaction on the connection of the resource's model, in the order of `resources`: the hooks and `perform*()` of each row run inside it, `destroyingAll()` and `destroyedAll()` (and their twins) inside it, before the first row and after the last, and a failure on any row rolls the others back.

## Confirmed writes

A resource can make `mutate`, `destroy`, `restore` and `forceDestroy` check that the user confirmed the write, as an action does with `confirmation()`. One hook per write, each returning `?Lomkit\Rest\Confirmation\Confirmation`, `null` by default:

```php
use Illuminate\Database\Eloquent\Collection;
use Lomkit\Rest\Confirmation\Confirmation;
use Lomkit\Rest\Mutations\MutationNode;
use Lomkit\Rest\Mutations\PendingMutation;

public function mutateConfirmation(PendingMutation $mutation): ?Confirmation
{
    $closed = array_filter($mutation->entries, fn (MutationNode $entry): bool => ($entry->attributes['status'] ?? null) === 'closed');

    return $closed === [] ? null : Confirmation::make('Close '.count($closed).' tickets?')->data(['rows' => count($closed)]);
}

public function destroyConfirmation(Collection $models): ?Confirmation
{
    return $models->count() < 10 ? null : Confirmation::make("Delete {$models->count()} tickets?");
}

// restoreConfirmation(Collection $models) and forceDestroyConfirmation(Collection $models) work the same way
```

`$mutation->entries` holds the root `MutationNode` entries, with their `relations`; `$mutation->row($this, $entry->key)` returns the stored row of an entry with a key, as the checks read it (`null` for a `create`). The models of `destroy`, `restore` and `forceDestroy` are the rows of `resources`, in their order. Only the hook of the endpoint's resource runs, not those of related resources. It runs after validation (`422`), the row checks (`403`) and, on a mutation, the version check (`409`), before the first write, once per request (not again on a deadlock retry). A confirmation answers `428` with the shape of an action confirmation and writes nothing: no `mutatingAll()`, `destroyingAll()`, `restoringAll()` or `forceDestroyingAll()`, no row hook, no event:

```json
{"message": "Delete 12 tickets?", "confirmation": {"token": "1796119500.4b1e…", "expires_at": "2026-12-01T10:05:00Z", "data": {}}}
```

The client sends the same body again with `"confirmation": "<token>"` and the write runs. The token is signed with `app.key` and valid for the same user and guard, resource, write and body (`mutate` and `response`, or `resources`; key order and unknown first-level keys do not count) until it expires, and can be used again until then; any other token answers a new `428` with a fresh token. Put the warnings of the application in `data` (string keys, `{}` when empty): the package gives no codes, and the message is translated on the server. The four bodies read `confirmation` as a string, also on a resource without the hook: another type is a `422`. Precognition stops before the confirmation. `details` with `with=confirmations` gives `"confirmations": {"mutate": false, "destroy": true, "restore": false, "forceDestroy": false}`: `true` for each hook that the resource declares; `restore` and `forceDestroy` only when the model uses `SoftDeletes`.

## Authorization model

Two layers, configured in `config/rest.php`:

### 1. Policy authorization

Every model touched by a search/mutate/delete is checked against its Policy. Required policy methods:

| Policy method | Triggered by |
| --- | --- |
| `viewAny` | `search`, `details`, `mutate`, `destroy`, `restore`, `forceDestroy`, `operate` and `metric` (unless the action's or the metric's `abilities()` returns `[]`), and every resource reached by an include, an aggregate or a relation filter (each level, pivot columns included), a relation group or a relation sort, and the `response` block of `mutate` (its resource and every resource it reaches) |
| `view` | per-row visibility, includes |
| `create` | `mutate` create: once per created resource class with the model class, then per created row with its draft (`create($user, ?Model $draft = null)`, without the values of `inputOnly()` fields); `details` with `with=authorizations` or `actions.authorized`, with the model class, answered in `authorizations` or in `authorized` |
| `update` | `mutate` update, with a `\Lomkit\Rest\Authorization\Changes` third argument (the values of `inputOnly()` fields included; `has()`, `get()`, `keys()`, and `target()`: the row a foreign key column points to, stored or created by the request); a child moved under a has-one/has-many relation |
| `replicate` | `mutate` create when duplicating from existing data |
| `delete` | `destroy` |
| `restore` | `restore` |
| `forceDelete` | `forceDestroy` |
| the abilities of `->abilities(attach: 'attachTag', detach: 'detachTag')` on a relation, `($user, $parent, $related)` | links of that relation, and the `pivot` of a `sync` entry on the rows it keeps attached; without them, `update` on the parent |

**Define a policy for every model you expose.** A relation that needs its own link check declares its abilities; a declared ability with no policy method is denied. `php artisan rest:policy PostPolicy --model=Post` writes a starting point that allows every ability: edit it before production.

**Class-level checks run before validation and before any query.** The request checks its endpoint abilities (`viewAny` for `search`, `details`, `mutate`, `destroy`, `restore` and `forceDestroy`, for `operate` `viewAny` followed by the resource's `Endpoint::Operate` abilities and the action's `abilities()`, and the same for `metric` with `Endpoint::Metric` and the metric's `abilities()`), then `viewAny` on every resource the payload reaches and `create` for every created resource. A denied check answers `403` (or the status of the policy's response) even for an invalid payload or a Precognition request. The request's own `authorize()` runs first, and both must pass. Row checks of `mutate` run in one batch inside the transaction, before the first write; those of `destroy`, `restore` and `forceDestroy` before their transaction.

To change the endpoint abilities of one resource, override `abilities()` on it:

```php
public function abilities(\Lomkit\Rest\Http\Routing\Endpoint $endpoint): array
{
    return $endpoint === \Lomkit\Rest\Http\Routing\Endpoint::Destroy ? ['viewAny', 'deleteAny'] : parent::abilities($endpoint);
}
```

The list replaces the endpoint's default: returning `['export']` for `Endpoint::Search` drops `viewAny` there. Keep `viewAny` in the list to add an ability (`['viewAny', 'export']`). `Endpoint::Operate` is the exception: its list adds to `viewAny` (see Custom actions).

These checks go through the resource's `authorizedTo()`, so an override of it applies to them. A denied check made before validation throws `\Lomkit\Rest\Exceptions\AuthorizationDenied` (an `AuthorizationException`) holding every denial of these checks (path such as `search.includes.0`, ability, resource class, key, message; at most 50). The body stays `{"message": ...}` with the message and status of the first denial, so `Response::denyAsNotFound()` still gives `404`. To add the denials to the JSON, register `$exceptions->render(new \Lomkit\Rest\Exceptions\DenialsRenderer);` in `bootstrap/app.php`'s `withExceptions()`; it leaves `404` responses unchanged and lists no denial whose status is `404`. The row checks of `mutate`, `destroy`, `restore`, `forceDestroy` and of actions (`authorizeEach()`) throw `AuthorizationDenied` with every denial (`mutate.1.relations.tags.0`, `resources.{index}`, `search`).

The queries check `viewAny` again on each resource they search (and `create` on each resource they create), reusing the decisions made before validation. The endpoint's own resource is the exception: its endpoint abilities stand in for `viewAny`, so when they leave `viewAny` out (a resource override of `abilities()`, or an action whose `abilities()` returns `[]`) the queries do not check it there. With `authorizations.strict = true` (default `false`, meant for test suites: set `REST_AUTHORIZATIONS_STRICT=true` in `phpunit.xml`), a check on another resource that was not made before validation throws `UnplannedCheckException`.

Within a request, each distinct check (ability, resource, model or model class) runs the policy once and the later checks reuse its decision, so `GateEvaluated` listeners and `Gate::after()` see one evaluation per distinct check. A decision read from the cross-request cache dispatches no `GateEvaluated` either. Policies run with `Gate::forUser($request->user())`, and a change of user starts a new set of decisions. The package drops the decisions on a model after writing it, and keeps them for a row that a mutation only attaches, detaches or leaves unchanged; a hook that writes a model itself calls `$request->context()->authorizer()->forget($model)`.

The cross-request cache keeps only what a resource declares in `authorizationCache(): array` (ability => seconds, for example `['viewAny' => 300, 'view' => 60]`); a resource that declares nothing keeps nothing. Declared decisions are stored as scalars in the store of `authorizations.cache.store` (`null`: the default store), keyed by guard, user class and key, resource, ability, model class, key and `updated_at`, the values of `authorizationCacheContext(): array` (for example `['team' => $this->request()->user()?->current_team_id]`; integers, strings, booleans or `null`) and a stamp per user, and are read wherever the ability is checked, write endpoints included. The key holds no tenant: with several tenants whose users share identifiers, return the tenant from `authorizationCacheContext()` of the base resource (`['tenant' => tenant()?->getTenantKey()]`), which also keys metric results, or name a per-tenant store; the tags of stancl/tenancy's `CacheTenancyBootstrapper` do not reach the package's `store()` call, and `forgetAuthorizationsFor()` drops the user's entries in every tenant of the store. Checks on unsaved models and the `update` checks of `mutate` are never kept. A row without `updated_at`, or written since the second its request started, keeps no decision; a search selects `updated_at` on a level that asks a kept row gate; a check on a row the request wrote is never kept. `Rest::forgetAuthorizationsFor($user)` drops a user's kept decisions and metric results (all guards, every store) and `Rest::forgetAuthorizations()` drops everyone's, guests included. `authorizations.cache.enabled = false` (`REST_AUTHORIZATIONS_CACHE_ENABLED=false`) turns it off for every resource.

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

Clients list `download` in `gates` next to the built-in abilities, at every level of that resource, and each row answers `authorized_to_download` (renamed in `rest.gates.names` like the others) from `download($user, $model)` on the policy. A declared gate is row-level only, never in `meta`, and a policy without the method denies it. A name is camelCase, listed once, neither `viewAny` nor a built-in ability: another value throws `InvalidResourceException` when the gates are first read (details with `with=gates`, or a search with `gates`). The details list every accepted gate under `gates`.

## Custom Response

`php artisan rest:response UserResponse` scaffolds a class with a `map($model, $responseModel)` method that returns the array shape sent to the client. `map()` is the only method to override: it receives each row of the page or the list, and each record of a mutation's `response`, as the package rendered it (`selects`, `gates`, `aggregates`, `includes`), and the package builds the rest of the response. `map()` reads the columns that the search loaded: list in `alwaysSelect()` a column that it reads and the row does not return. Assign on the Resource:

```php
public static string $response = \App\Rest\Responses\UserResponse::class;
```

Use sparingly — overriding the response can bypass authorization shaping and field whitelisting if you reference the model directly.

## OpenAPI documentation

The package documents its endpoints through Scramble.

```bash
composer require dedoc/scramble:^0.13.48
```

The package registers its documentation itself: `config/scramble.php` needs no change. The document is the same for every reader: the package reads the declarations of each resource as a guest, without a user. It documents one operation for each route of an action or a metric, and leaves out the routes `{resource}/actions/{action}` and `{resource}/metrics/{metric}` that only answer an unknown name. The package writes the responses of its operations; Scramble keeps inferring the responses of the other routes of the application.

Each operation of the package lists the shared error responses of `components.responses`: `RestUnauthenticated` (`401`) when its route runs the `auth` middleware, `RestForbidden` (`403`) and `RestNotFound` (`404`) on every operation, `RestConflict` (`409`, its `conflicts` items one of two shapes by `reason`: `version` with `path`, `key` and `version`, `unlisted` with `path` and `key`) on a mutate operation that writes a `versioned()` resource or reaches a `syncable()` relation, at its root or under its relations, and `RestValidationFailed` (`422`) on every operation but batch progress (details validates its `with` parameter), and `RestConfirmationRequired` (`428`, `{"message", "confirmation": {"token", "expires_at", "data"}}`) on the action operations whose action declares `confirmation()` and on the `mutate`, `destroy`, `restore` and `forceDestroy` operations of a resource that declares the matching hook (`mutateConfirmation()`, ...), whose body also documents `confirmation` (a string). An operation whose route does not run `auth` has `"security": []`. The application declares its security scheme:

```php
Scramble::configure()->withDocumentTransformers(function (OpenApi $openApi): void {
    $openApi->secure(SecurityScheme::http('bearer'));
});
```

Each field documents its type with `toSchema()`: `ID` an integer or a string from the key type, `Text` a string, `Number` a number, `Integer` an integer, `Boolean` a boolean, `Date` a string with `format: date`, `DateTime` a string with `format: date-time`, `Enum` an enum of its backing values (the `offered()` ones where a value is written), `Json` an object; `nullable()` adds `null`, `meta()` goes under `x-lomkit-meta`. A field type of the application overrides `toSchema()` to return its Scramble type (a `string` otherwise). `->examples('Weekly report', 'Daily report')` lists example values on any field; each call replaces the list, and the request examples use the first value.

Each resource has a record in `components.schemas`, named like its tag (`Post`): its selectable fields with their type, the gates of a row, and its relations under their response keys (to-one: the target record or `null`; to-many: a list, with the `pivot` of a many-to-many relation; `MorphTo`: `oneOf` the `{Resource}WithType` records of its targets, discriminated by `@type`); a morph type column is an enum of its types. The search answers `{Resource}Paginated` and the three deletion endpoints `{Resource}Rows`; their body requires `resources`, typed by the `ID` field, and carries a request example with one key (the first `examples()` value of the `ID` field, else `1` or `a`). A second resource with the same name gets its namespace in its record names (`App.Rest.Admin.Tag`). A record lists `@more` when its resource has a to-many relation, and the page of a resource with `$maxTotal` holds `total_capped` (optional with `ScoutEngine`). Cast `Date` attributes with `date:Y-m-d` so that responses match the documented `format: date`.

Docs are served at `/docs/api`. The package builds the document from the declarations of the resources and reads no rule as a type. The search `filters` document one condition per filterable field, per filterable field of each related resource one level deep (`author.last_name`) and per filterable pivot field (`tags.pivot.weight`), with its operators and the value that each one takes, one condition on the related rows of each relation (`exists`, `nested`, and `types` for `MorphTo`), and groups of filters; deeper dotted paths are accepted and are not listed. The search body is `{Resource}Search`, open at its root (`text` and `trashed` as `false` where the resource does not support them) and closed to their keys below it: includes per relation with the search of the related resource, aggregates per relation with the fields of each function, scopes with their typed parameters, instructions with their typed fields, scope parameters and instruction fields with their rules in the description and their default under `default`, the sorts with the fields of the to-one relations and the default key of each aggregate that sorts (`comments_count`), the gates (left out when `isGatingEnabled()` returns `false`), the page sizes under `limit`, and the list sizes of `rest.limits`. Each search operation has a request example that filters on the first filterable field that declares `examples()`, else on the first filterable field. The search `text` (with the `mode`s of its strategy), `trashed` (on `SoftDeletes` models) and the `@relevance` sort only appear on the resources that support them. The mutate body is a list of `{Resource}Mutation` entries, `oneOf` a create and an update (`key` typed by the `ID` field, and `version` typed like the `updated_at` field on a `versioned()` resource), each closed to its keys: `attributes` lists the fields that the operation writes (`{Resource}Create`, `{Resource}Update`) with their type and their rules in the description, and a writable morph type column as an enum of its types; `relations` (`{Resource}Relations`) lists each relation but the through relations, required or left out per operation by `requiredOnCreation()` and the other constraints, and the entry then requires `relations`. The entry of a relation groups the operations of its `acceptedOperations()` by the keys that they take (`key`, a key or a list under a relation to many; `attributes`; `pivot` with the pivot fields that the operation writes; `without_detaching` on `sync`, required with `const: true` on a many-to-many relation that does not accept `detach`), plus `delete` with `key` alone on a `deletable()` relation and `sync` with `key` alone on a `syncable()` relation, whose list describes the check; a mutation that reaches such a relation lists the `409`, and a `MorphTo` relation with several types has one entry per type, discriminated by `type`. Each mutate operation has a request example that creates a row from the first `examples()` value of each writable field, else from a value of its type that honours its simple rules (`min`, `max`, `size`, `between`, `gt`, `gte`, `lt`, `lte`, `in`, `email`, `url`, `uuid`, `ulid`, `ip`, `date`, `numeric`, `digits`, `accepted`, `declined`; `min:8` gives `aaaaaaaa`), plus an `attach` of the example key for each relation declared `requiredOnCreation()`; declare `examples()` on a field whose other rules the example does not pass. The `response` block lists the `selects`, `aggregates`, `includes` and `gates` of a search, and a mutation answers `{Resource}MutateResult`: `operations` (`RestMutateOperation` entries, with their `relations`), then `records` when the request sends `response`. Each action operation documents its body by target: `search` (`{Resource}ActionSearch`) or `resources` (from 1 to `$maxResources` keys), the key that the target refuses as `false`, and `fields` as typed `{"name", "value"}` entries with their rules in the description and their default under `default`; its request example gives one entry for each field without a default (a request example never sends a default), in the order of `fields()`, from its first `examples()` value, else from a value of its type that honours its rules. A `destructive()` action carries `"x-lomkit-destructive": true`, an action with `confirm()` its text under `x-lomkit-confirm`, both also in the description, with the 428 of `confirmation()`. An action answers `RestActionResponse` (`impacted`, `result`), or `RestBatchableActionResponse` (with `batch`) when it implements `BatchableAction`; `result` is `RestActionResult`, `oneOf` the types of `ActionResponse` (`message`, `danger`, `download`, `redirect`, `created`, `data`) discriminated by `type`, each with the optional `data` of `withData()`. The batch progress operation lists the batchable actions in its `action` parameter and answers `RestBatchProgress`. Each metric operation documents its body (`search` as `{Resource}MetricSearch`, `range` as an enum of its ranges with the first one as `default`, or `false` without ranges, `timezone`) with the request example `{}`, and answers `data`, `allOf` its `type` and the `schema()` of the metric: `RestValueResult`, `RestTrendResult`, `RestPartitionResult` (`previous` on each part and on `other` when the partition compares two periods), `RestTableResult` (`percentile` on a percentile column) or `RestProgressResult` for the built-in types. The details operation documents its `with` query parameter (the accepted values in its description) and its response in one `{Resource}Details` schema per resource, whose base keys are required and whose sections and parts are optional, and which lists the names of the fields, relations, scopes, instructions, actions, metrics and gates of the resource as enums, with its entries: the field entries, the relations with `operations`, `sync`, `pivot_fields`, `types` and `morph`, the rules, `text`, `gates`, the input entries of actions, instructions and scopes, and `meta`, which accepts no key unless the resource overrides `detailsMeta()`, then any key. The `meta` of `{Resource}Paginated` accepts the gates key alone, plus `instructions` (an object of objects) when an instruction of the resource overrides `searchMeta()`, and any key when the resource overrides `searchMeta()`.

## Precognition

Set `rest.precognition.enabled = true` (default `false`). Clients send `Precognition: true` (and optionally `Precognition-Validate-Only`) on any endpoint, and the request is authorized (the class-level checks above, a denied one answers `403`), validated and short-circuited before the controller body runs — `204 No Content` on success, `422` on validation failure. Useful for live frontend form validation. A name in `Precognition-Validate-Only` also covers the keys under it: `search` validates `search.limit`, `search.filters.0.field` and every other key of the search, while `search.filters` leaves `search.limit` out. A name also reports the errors that block its own validation: the errors of each key above it (`search.filters`, `search.filters.0`, `mutate.0`) and of the keys that decide the rules of an entry above it — `operation`, and `key` when the operation takes one, for a mutate entry (so `mutate.0.attributes.title` runs the key query for `mutate.0.key`), `type` for a `MorphTo` entry, `field` and `operator` for a filter, `relation` for a relation group, an include or an aggregate, `type` for an aggregate, `name` for a scope or an instruction, `field` for a sort, and the `name` of the entries that set an action or instruction field (`fields.0.name` for `fields.status`). The other keys beside them stay out, so `search.filters.0.field` does not report `search.filters.1.value` or `search.limit`.

## Artisan commands shipped by the package

- `rest:resource {Name} --model=Model` — generate a Resource. Without `--model`, the model is guessed from the name (`PostResource` → `App\Models\Post`) when that class exists; otherwise the `$model` line is left out and the command warns.
- `rest:resource {Name} --model=Model --generate` (`-G`) — read the model's table and declare one field per column: the key as `ID::make(<key name>)`, the type from the cast or the column type (`Enum` for a backed enum cast, `Json` for JSON casts, `AsFluent`, `AsVector` and `json` or `jsonb` columns (on SQLite, a `json` column is text unless the connection sets `use_native_json`, and a `jsonb` column unless it sets `use_native_jsonb`, so `Text` without a JSON cast), unknown types as `Text` with a note), `nullable()` for nullable columns, `readonly()` for timestamps and auto-increment or generated columns, `writeOnly()` for `$hidden` columns (a hidden column the model does not let fill is left out with a note, and so are `remember_token`, or the `getRememberTokenName()` of an `Authenticatable` model, `two_factor_secret`, `two_factor_recovery_codes` and `two_factor_confirmed_at`), `filterable(false)` and `sortable(false)` for the other columns with an `encrypted` cast, `rules('max:N')` when MySQL or PostgreSQL report a string length, and `creationRules('required')` plus `Rule::unique(Model::class, 'column')` for required and uniquely indexed columns, with `updateRules(Contextual::rule(fn (MutationContext $mutation) => Rule::unique(Model::class, 'column')->ignore($mutation->key)))` on a unique column other than the key (the key name follows `$mutation->key` when it is not `id`). It also declares the relations: each public model method with a declared Eloquent relation return type (`BelongsTo`, `HasOne`, `HasMany`, `BelongsToMany`, `MorphOne`, `MorphMany`, `MorphToMany`, `morphedByMany()` as `MorphedByMany`, `HasOneThrough`, `HasManyThrough`) whose related model has a resource under `app/Rest/Resources` (or is the model itself), e.g. `BelongsTo::make('author', UserResource::class)->requiredOnCreation()` with `author_id` made `readonly()`, `->withoutOperations(MutationOperation::Detach)` when the column that a detach sets to null does not accept null (the foreign key of a `BelongsTo` in the model table, the foreign key or morph type of a `HasOne`, `HasMany`, `MorphOne` or `MorphMany` in the related table), and `pivotFields([...])` typed from the pivot table for `withPivot()` columns and `withTimestamps()` (pivot timestamps `readonly()`; a declared column missing from the pivot table is left out with a note). Relations to a model without a resource, and `MorphTo`, are skipped; the `MorphTo` note prints `MorphTo::make('<name>', [...])` with the resources of the morph map whose model points back to it (a `morphOne()`, `morphMany()` or `morphToMany()` with the same morph name), and no example without one. It prints `PostResource ... 8 fields, 1 relation`, one `Skipped:` line per relation not generated (with the `rest:resource` command to run first for a missing resource) and one `Note:` line per column to review. It exits 1 before writing anything when the model is missing, is not a model or cannot be instantiated (an abstract model), or its table is missing; rerun with `--generate --force` to regenerate an existing resource. Interactively, without the flag, it asks `Generate the fields and relations from the <table> table?` (default yes) when the table exists; with `-n` and no flag the resource declares only the key. Review the generated fields before exposing the resource.
- `rest:controller {Name} --resource=ResourceClass` — generate a Controller. Without `--resource`, the resource is guessed the same way (`PostsController` → `PostResource`); otherwise the `$resource` line is left out and the command warns. Set `$resource` before you register the controller with `Rest::resource()`: a controller without it stops the route files and every Artisan command, the generators included.
- `rest:base-resource` / `rest:base-controller` — generate the empty abstract base classes `app/Rest/Resources/Resource.php` and `app/Rest/Controllers/Controller.php` that all your resources/controllers should extend. `rest:resource` and `rest:controller` create them when they are missing. Override the `*Query` hooks in the base resource to constrain every resource.
- `rest:action {Name}` — scaffold an Action in `app/Rest/Actions`; list the class in `actions()` (`CloseTickets::class`).
- `rest:instruction {Name}` — scaffold an Instruction.
- `rest:metric {Name} --type=value|trend|partition|progress|table|custom --group-by=field` — scaffold a metric in `app/Rest/Metrics`: `value` (`$this->count($query)`), `trend` (`$this->countByDays($query)` by `created_at`, ranges 7, 30 and 90 days), `partition` (`$this->count($query, '<field>')`), `progress` (`$this->count($query, target: 100)`), `table` (`$this->rows($query, '<field>', ['count' => TableColumn::count()])`), or `custom` (extends `Metric`, `type()` is the class name in kebab case, `calculate()` returns a `ValueResult` to replace with your own result). `--group-by` is required for `partition` and `table` and ignored, with a warning, by the others. Interactively it asks for the type, then for the field; with `-n` and no `--type` it writes a `value` metric. List the class in `metrics()` (`OrdersByStatus::class`).
- `rest:response {Name}` — scaffold a custom Response class.
- `rest:policy {Name} --model=Model` — generate a policy that allows every ability in `app/Policies`: `viewAny`, `view`, `create`, `update` and `delete` (plus `restore` and `forceDelete` with `SoftDeletes`) return `true`. Edit it before production. The user parameter is typed with the model of the default guard's user provider. Without `--model`, the model is guessed from the name (`PostPolicy` → `App\Models\Post`). A model that Gate already maps to another policy keeps it: nothing is written, and with `--force` the file is written but Gate still uses the other policy until you register the new one with `Gate::policy()`.
- `rest:quick-start --model=Post --model=Tag` — for each model, write the resource from its table (as `--generate`), the controller and, when Gate finds no policy, a policy that allows every ability, then append `Rest::resource('posts', PostsController::class)->middleware('auth:sanctum')` to `routes/api.php` once per controller (`auth:<guard>` with `--guard=<guard>`). Related models that are not chosen are skipped. Interactively it asks for the models, and for the guard when an install is needed and `--guard` is not passed, `--install-api` included; with `-n`, `--model` is required, the guard is `sanctum` unless `--guard` is passed, and `--install-api` is required when `routes/api.php` is missing, or when the run adds routes and the package of the guard (Laravel Sanctum or Laravel Passport) is missing. It ends with a check in a fresh process (routes authenticated, a policy found per model) and exits 1 when blocked. Existing files are reported as `kept`, so a rerun changes nothing.
- `rest:check` — check the resources that the routes reach, and exit 1 on any problem (run it in CI). Without arguments it checks every controller that a route uses; `rest:check PostsController` checks the named controllers. It reports each route without an authentication middleware or with an undefined guard, each controller model without a policy, each action or metric of these controllers that throws when it is built (it builds every one: `declarations ... 4 actions, 2 metrics built`), each resource reached by these controllers, or through their relations, that does not override `perimeterQuery()` (`every row is visible to users who pass viewAny`), and each action on the `Search` or `Resources` target whose `authorizeEach()` returns null, and each relation that accepts `detach` while its foreign key or morph type does not accept null (`... accepts detach on its [team] relation, but tickets.team_id does not accept null`: add `->withoutOperations(MutationOperation::Detach)` or make the column nullable). This check reads the database schema (`Schema::getColumns()`): a relation whose table does not exist yet is listed as skipped (`... [country] ... skipped, no trips table`), and a schema that cannot be read is a problem that names the table and the connection. Mark a resource whose rows are meant for every user who passes `viewAny` (countries, currencies) with `#[\Lomkit\Rest\Attributes\WithoutPerimeter]`: it is listed as such, not as a problem, and a subclass inherits the attribute; a parent resource of the application that overrides `perimeterQuery()` counts for its subclasses. An action that only reads returns `'view'` from `authorizeEach()`. `--json` prints `routes`, `policies`, `problems` (routes and policies), `search`, `declarations` (`actions` and `metrics` built, `problems`), `rows` (`perimeter`, `without_perimeter`, `problems`), `actions` (`checked`, `problems`) and `relations` (`detachable`, the other relations that accept `detach`, `skipped`, with their `table`, and `problems`).
- The generators keep an existing class unless `--force` (`-f`) is passed, and read the published `stubs/rest.<name>.stub` before the package stubs. Stubs published under 2.x generate untyped properties, which are a fatal error in 3.x: delete them, or publish them again with `php artisan stub:publish --force`.

## Working conventions and gotchas

- **Don't write custom controller methods.** If you find yourself wanting to, the right tool is almost always an `Action` (mutation) or an `Instruction` (query). Falling back to a plain Laravel controller bypasses the package's authorization layer.
- **Whitelist by declaration.** Adding a column to the table doesn't expose it — it must be in `fields()`. Same for scopes, relations, actions, instructions.
- **A search loads only the columns it returns.** A gate's policy, a `resolveUsing()`, `map()` or `searched()` that reads another column throws `Lomkit\Rest\Exceptions\MissingColumnException` (a `500` naming the resource, the column and the model); an observer or another accessor gets `null` (a `MissingAttributeException` with `Model::preventAccessingMissingAttributes()`): list the column in `alwaysSelect()` or in the field's `requires()`, or set `rest.search.select_columns` to `false`. Actions, `destroy`, `restore`, `forceDestroy` and mutation lookups still load every column. A `select()` in a scope or an instruction is kept and the search adds its columns, but a `<table>.*` in it (also the one of `$withCount`) is replaced by them; a `select()` in a global scope runs after the search and replaces them.
- **Relation classes are the package's, not Eloquent's.** Use `Lomkit\Rest\Relations\HasMany` (not `Illuminate\Database\Eloquent\Relations\HasMany`), pass `::make('name', TargetResource::class)`, and target the **Resource**, not the model.
- **Soft deletes are opt-in at the route.** Even if the model uses `SoftDeletes`, `restore` / `force` routes only register when `->withSoftDeletes()` is chained onto `Rest::resource(...)`.
- **Don't bypass policies by overriding `isAuthorizingEnabled()`** — write the policy. The package expects every policy to run, including `viewAny`, `replicate`, and the link abilities that relations declare.
- **`mutate` is one batch but per-item-authorized.** A single denied item fails the whole batch.
- **`includes` nest up to `rest.limits.depths.includes` levels** (default `3`) — each include accepts `relation`/`alias`/`filters`/`sorts`/`scopes`/`instructions`/`aggregates`/`selects`/`gates`/`limit` and its own `includes`; `page`, `text` and `trashed` are a `422`, and so are `limit` and `sorts` on a `BelongsTo` or `MorphTo` include. Without `limit`, an include returns at most `$includeLimit` rows (`10`) per parent row. A row of a level with a to-many include lists under `@more` the includes that have more rows. Each relation of a dotted include counts as one level. A deeper `includes` is a `422`, `The search.includes.0.includes.0.includes.0.includes field is prohibited.`, and a dotted include that goes deeper gives `The search.includes.0.relation field goes deeper than the limit of 3 include levels.`; raise the config value to go deeper.
- **`=` or `!=` with `null` is a `422`.** Filter the rows without a value with `{"field": "summary", "operator": "is null"}`, on a `nullable()` field only.
- **Action `fields` payload is an array of `{name, value}` pairs**, not an object — easy mistake: an object keyed by name is a `422`, `The fields field must be a list.` The same goes for instruction `fields`.
- **One validator per request.** A package request turns its payload into rules on explicit keys, then validates them in one pass, so `withValidator()` and `after()` on a request subclass run once and see every error. Every payload is parsed into rules on explicit keys (`search.filters.0.field`, `fields.0.name`, `mutate.0.attributes.name`), and `SearchRequest::payload()`, `OperateRequest::payload()` and `MutateRequest::payload()` return it as typed nodes (`Lomkit\Rest\Search\SearchNode`, `Lomkit\Rest\Actions\OperationNode`, a list of `Lomkit\Rest\Mutations\MutationNode`) once the request passed validation. An override of `rules()` or `passedValidation()` must call the parent, and a rule that it adds for a parsed key after `...parent::rules()` adds to the package rules of that key and runs after them. Laravel expands the wildcard rules of `rules()` (`mutate.*.attributes.name`) and of the resource rules, wherever the override writes them. A list over its `rest.limits.sizes` value gets one `422` on its own key, and the package reads, checks and validates nothing under it; a wildcard rule of `rules()` with more than one `*` (`mutate.*.relations.products.*.attributes.name`) still runs on the entries of a nested list over its limit. The package has no rule nodes any more: the `rules()` of a request hold Laravel rules only. The `label()` and `messages()` of fields are added under the request's `attributes()` and `messages()`, which still come first for a key or a key pattern. To adjust the validator, use `withValidator()` or override `createDefaultValidator()` and call the parent: a request that defines `validator()` must build it from `validationData()` and `validationRules()`, and loses the labels, messages and names that the package gives to keys. Overrides of `validationData()`, `validationRules()`, `createDefaultValidator()` and `shouldValidatePrecognitiveAttribute()` must keep the parent's return types.
- **Scope `parameters` are positional.** They bind to the scope's fields in declared order, so reordering a scope's fields changes what clients must send. Only model local scopes run: `withTrashed`, `latest` and other Builder methods or macros cannot be called as scopes.
- **`HasOneThrough` / `HasManyThrough` can be searched/included but not mutated.**
- **Deadlock retries.** `transactions.attempts` (default `1`, `REST_TRANSACTION_ATTEMPTS`) runs the transaction of a mutation, a destroy, a restore, a force destroy, or an action chunk, row or whole run again when the database reports a deadlock or a lock timeout, at once, from the rows as read, with its row checks, hooks, events and `handle()` (or `handleRow()`, not `beforeRow()`); only the outermost transaction is run again (inside an app transaction: `DeadlockException`). Send mails, HTTP calls and jobs after the commit (`afterCommit()`, `ShouldDispatchAfterCommit`) so they run once.
- **Cache invalidation.** A declared decision on a row follows the row's `updated_at` (Eloquent writes only, second precision). A change to a user's roles or to what a `Gate::before()` callback reads needs `Rest::forgetAuthorizationsFor($user)`; a policy change needs `Rest::forgetAuthorizations()` or the seconds of the ability. Set `REST_AUTHORIZATIONS_CACHE_ENABLED=false` while debugging policies, or return `[]` from the `authorizationCache()` of a resource.
- **`config/rest.php` values are typed.** The switches take `true`, `false`, `"1"`, `"0"`, `"true"` or `"false"` (so `env()` values work), `gates.key`, `morph.type_key` and `search.more_key` a non-empty string, `gates.names` a map of strings, `authorizations.cache.store` `null` or a non-empty string, every `limits` value an integer of 0 or more (`totals.relations` and `sizes.jobs` 1 or more), and `transactions.attempts` an integer of 1 or more. A value of another type and an unknown key under `rest.limits` (such as `sizes.filter`) throw `Lomkit\Rest\Exceptions\InvalidConfigException`, which names the key. The 2.x keys `gates.enabled`, `gates.message`, `authorizations.enabled`, `search.max_nesting_depth`, `authorizations.cache.default` and `documentation` are removed: override `isGatingEnabled()` or `isAuthorizingEnabled()` and return `false` instead of the switches, `limits.depths.filters` instead of `search.max_nesting_depth`, and `authorizationCache()` instead of `authorizations.cache.default`.

## Pointers into the codebase

- Service provider & routing — `src/RestServiceProvider.php`, `src/Rest.php`, `src/Http/Routing/` (`Endpoint`, `PendingResourceRegistration`, `EndpointRoutes` (the routes of each endpoint, one per action for `operate` and one per metric for `metric`), `ResourceRegistrar` and the route value objects)
- Metrics — `src/Metrics/` (`Metric`, `MetricResult`, `HasMetrics`, `MetricQuery`, `MetricNode`; the built-in types `Value`, `Partition`, `Progress` and their results; `MetricRange`, `MetricColumn` (typed checks), `Aggregate` and `AggregateQuery` (the SQL), `Percentile` and `PercentileQuery` (the SQL of a percentile, by database in `Grouping/`), `HasLimit`, `RankedGroups` and `MeasurePeriods` (the groups and splits, sorted and limited in SQL); `ResultSchemas` (the OpenAPI schemas of the built-in results); `Trend`, `TrendResult`, `Periods` and `PeriodUnit`, and `Grouping/` (the SQL of a period per database); `Table`, `TableColumn` and `TableResult`; `HasCache`, `CacheScope`, `MetricKey` and `CacheMetricStore` (the result cache)), `src/Http/Requests/MetricRequest.php`, `src/Http/MetricResponse.php`
- Config — `src/Config/RestConfig.php` (the typed values of `config/rest.php`, read once per request through `RestContext::config()`) and `src/Config/ConfigValue.php` (the typed read of one key, also used for `rest.limits` and `rest.precognition.enabled`)
- Request classes per verb — `src/Http/Requests/{Search,Mutate,Operate,Destroy,Restore,ForceDestroy,Details,Batch}Request.php`; the details response in `src/Http/Details/` (`DetailsSelection`, the values of `with`, and `ResourceDetails`, which builds the requested sections)
- Resource base class & traits — `src/Http/Resource.php`, `src/Http/ResourceDefinition.php` (the declarations of one resource for one request, read with `$resource->definition()`, and their lookups `relation()`, `scope()`, `action()`, `instruction()` and `inputFields()`; kept by `src/Http/RestContext.php`, whose limits are a `src/Http/ResourceLimits.php`), `src/Concerns/Resource/*` (`DeclaresFields`, `DeclaresScopes`, `Paginable`, `Relationable`, `Contextual` with `definition()` and `request()`, hooks, ...)
- Authorization — `src/Authorization/*` (`Authorizer`, `ActingUser` (the guard and user that a `RestContext` checks for, when it is not the user of the request), `Check`, `Changes`, `Decision`, `Denial`, `DecisionMemo` and the decision stores), `src/Concerns/Authorizable.php`, `src/Exceptions/{AuthorizationDenied,DenialsRenderer,UnplannedCheckException}.php`; the checks made before validation are collected by the parsers (`ParsedRules::reach()`), and run by `RestRequest::passesAuthorization()`
- Query layer — `src/Query/PageQuery.php` (the default `paginate()`: the page first, the count only when the page does not give the total), `src/Query/EngineSearch.php` (the search of a `ScoutEngine` text) and `src/Query/SqlSearch.php` (the search in the database), the two `SearchSource` implementations that read one page, `src/Search/Run/SearchRun.php` (the search of a resource from a validated `SearchNode`, which the search endpoint runs: `searching()`, one page from its source, the include overflow trimmed, `searched()`; its `SearchedPage` builds the response; `lazy()` reads the keys of every row once with `SqlSearch::chunks()`, then loads, trims and renders them by chunks of keys with `src/Actions/Rows/KeyChunks.php`), `src/Search/Run/PendingSearch.php` (the builder that `Rest::search()` returns: `as()`, `payload()`, `page()`, `render()`, `lazy()`) and `src/Search/Run/PayloadSearchRequest.php` (the `SearchRequest` that validates the payload of a `PendingSearch` for its user), `src/Query/AttributeWriter.php`, which fills a model from the writable fields of a mutation, `src/Query/RelationAggregate.php` (the subquery of an aggregate over a relation, selected by `AggregateClause` and ordered by `RelationSortClause` and `AggregateSortClause`), and `src/Search/Plan/*` (`SearchPlanner`, `QueryPlan` (its `query()` starts from the query of the model and applies the plan), `PlannedPage` (the plan and the rows of a search engine page), `PlannedRows` (the plan of `lazy()` and the `KeyChunks` of its rows), `LimitPolicy`, the row sources `SqlRows` and `KeyedRows` (the rows of a `KeySet`: the `EngineKeys` of an engine page, or the `RecordKeys` of the records of a mutation), the column selection in `src/Search/Plan/Columns/` (`ColumnSet`, the columns of one level; `ColumnSelection`, the returned columns or every column; `RelationKeys`, the keys of each Eloquent relation class), and the `IncludeClause`, `AliasedIncludeClause` (an alias loaded on copies of the parent rows), `MorphToIncludeClause` (a `MorphTo` include, loaded per stored type) and `DefaultOrderClause` clauses)
- Responses — `src/Http/Response.php` (the envelope, `map()` and `rows()`), `src/Http/MutateResponse.php` (the mutation in one transaction, its `operations` and, with `response`, its `records`, read by `src/Http/WrittenRecords.php`), `src/Http/OperateResponse.php` (`impacted` and the `result` of an action), `src/Http/BatchProgress.php` (the progress of a batch) and `src/Http/Serialization/*`: `RowSerializer` renders the rows of one plan level with `RowFields` (which converts only the returned columns), `GateTable`, `RelationRenderer` and `PivotRenderer`, and `MorphToSerializer` the targets of a `MorphTo` include, type first (both are `RowRenderer`s), and `PageEnvelope` or `PlainEnvelope` (the `Envelope` strategies) wraps them; `src/Pagination/LengthAwarePaginator.php` gives the page keys
- Writes — `src/Mutations/Mutator.php` (writes the entries of `payload()` in the transaction of `MutateResponse`, reads each row from the `RowSet` that the key check of validation loaded (`src/Mutations/RowSet.php`, one instance per row) and collects the entries of the response in an `OperationLog` of `OperationEntry` objects) and `src/Mutations/RelationWrites.php` (the writes of each Eloquent relation class: `OwnerWrites`, `ChildWrites`, `PivotWrites` with `PivotSet` and its `PivotPlan`, `NoWrites`), checked before the first write by `src/Mutations/WriteChecks.php`; the pivot rows are written by a `src/Contracts/PivotPersister.php` (`persistedBy()` in `src/Concerns/Relations/PersistsPivots.php`), called with a `src/Mutations/PivotWrite.php`: `EloquentPivots` by default, a declared one wrapped in `VerifiedPersister`, which reads the links back with `LinkedKeys`
- Text search — `src/Text/*` (`TextSearch`; the SQL strategies `Contains` and `FullText` implement `QueryTextSearch`, with the database dialects in `src/Text/Dialects/`; `ScoutEngine` implements `EngineTextSearch`; `ResolvedText`, the text search of a resource for one request, read through `$request->context()->text($resource)`; `TextQuery`, `TextMode`, `TextCapabilities`), applied by `TextClause`, `RelevanceClause` and `TrashedClause` in `src/Query/Clauses/`
- Search engines — `src/Text/Engine/*` (`EngineQuery`, `IndexAttributes`, `EnginePage`, `ScoutModel`) and one profile per Scout engine in `src/Text/Engine/Profiles/`; engine filters and sorts are validated by `src/Text/Engine/{EngineSearchGrammar,EngineFilterParser}.php`, and actions read their keys through `src/Actions/{EngineTargets,EngineTargetKeys}.php`; `EngineCriteria` and `EngineInstruction` build the engine query from the nodes of `payload()`
- Confirmations — `src/Confirmation/` (`Confirmation`, the answer of a hook; `ConfirmationGate`, the check of one request, which signs a `ConfirmationToken`), `src/Concerns/Resource/ConfirmsWrites.php` (the hooks of the writes), `src/Mutations/PendingMutation.php`, `src/Exceptions/ConfirmationRequired.php` (the `428`)
- Actions / Instructions / Scopes — `src/Actions/{Action,ActionTarget,ActionFields,ActionResponse,RowChecks,Actionable}.php` (`RowChecks`: the `authorizeEach()` checks of an action), `src/Actions/Rows/*` (`ChunkedRows`, `KeyChunks`, `NoRows`: the rows of each target; `CheckedRows`: the rows of a `ChunkedRows` that passed `authorizeEach()`; `RowCap`: `$maxResources`), `src/Actions/BatchOwner.php`, `src/Instructions/{Instruction,InstructionFields,Instructionable,AppliesToEngine}.php`, `src/Scopes/Scope.php`
- Typed fields and input values — `src/Fields/*` (`Field`, `Concerns/ConfiguresWriting` (`creatable()`, `updatable()`, `writeOnly()`, `inputOnly()`, `fillUsing()`), `Concerns/ConfiguresMessages`, `FieldSet`, `FieldPath`, `InputValues`, `CastValues`, `FieldValues`, `MutationOperation`, `WriteRules`, `Details/FieldDetails`, `Details/ResourceRuleDetails`, `Details/InputFieldDetails`), filter operators and conditions in `src/Fields/Filtering/`, clauses in `src/Query/Clauses/`, built from the nodes of `payload()` with `of()` (filters: `FilterGroupClause`, `FieldConditionClause`, `RelationConditionClause`, `RelationGroupClause`)
- Relations — `src/Relations/*.php` (the relation classes follow the Eloquent hierarchy: `MorphTo` extends `BelongsTo`, `MorphToMany` and `MorphedByMany` extend `BelongsToMany`, `HasOne`, `HasMany`, `MorphOne` and `MorphMany` extend `HasOneOrMany`, the through relations extend `HasOneOrManyThrough`) (`MorphColumns`, the columns of a `MorphTo` in the details); `src/Contracts/PolymorphicRelation.php` and `src/Relations/MorphTargets.php` (the targets of a `MorphTo`, by morph type); `src/Contracts/OwnerRelation.php` (`BelongsTo` and `MorphTo`: one row per parent, no include limit); pivot fields in `src/Concerns/Relations/HasPivotFields.php` (`src/Contracts/PivotRelation.php`), validated by `src/Validation/Mutate/EntryRules.php`
- Validation of the request bodies — the parsers in `src/Validation/{Search,Operate,Mutate}/*` (the search parser with `SearchGrammar`, `SqlSearchGrammar` (with `SortPaths`, the sort names of a level and its relation paths) and `LevelBounds`, the bounds of a level, and `RowKeys`, the keys of a row, read by the `IncludeAlias` and `AggregateAlias` rules), the typed nodes in `src/Search/*` (with the sort nodes `FieldSort`, `RelationSort`, `AggregateSort` and `RelevanceSort`, `AggregateKey`, and `SearchContext`, which `FilterFlattener` builds from the filters as `FlatFilter` entries), `EngineFilterCondition` and `EngineSort` in `src/Text/Engine/`, `src/Actions/OperationNode.php` and `src/Mutations/*` (`MutationNode`, `MutationContext`, `Contextual`, `MutationAwareRule`), and `src/Validation/{ValidationContext,Vocabulary,ParsedRules,PayloadReader,InputSet,CompiledRules,MessageBook}.php`
- Request limits and resource keys — `src/Validation/{Limits,ListSizes,Depths,Totals,LimitGroup,NodeBudget,Cursor,Violation,Violations,Rejected,KeysGrammar,KeyList,KeyLookup,KeyRead,ExistingKey}.php` and `src/Http/Requests/Traits/ParsesResourceKeys.php` (the `resources` of the deletion requests, whose key query loads their rows; the operate parser reads the `resources` of the actions with the `Resources` target)
- OpenAPI documentation — `src/Scramble/RestOperations.php` (the Scramble operation transformer: it builds a `DocumentationContext` as a guest for each route of the package and hands the operation to the documenter of its endpoint in `src/Scramble/Endpoints/`, `EndpointDocumenters`) and `src/Scramble/RestDocument.php` (the document transformer: it leaves out the catch-all routes), `ErrorResponses` (the shared error responses) and `ComponentRegistry` (one name per component of the document), `src/Scramble/Components/` (`RecordSchema`, the record of a resource, `PageSchema`, its page and changed rows, `KeySchema`, its key list), `FieldSchema` (the type of a field), `Types/OneOf`, with `Schemas` (the shared object, list, request body and response builders), `Search/FilterSchema` (the filters of a search), `Search/FilterPaths` (the filterable paths of a resource), `Search/FieldFilterSchema` (the condition on one field), `Search/SearchExample` (the request example of a search), `FieldExample` (an example value of a field), `Types/Nothing` (a schema that accepts nothing, for `additionalProperties` and the keys that a body refuses), `Search/SearchSchema` (the body of a search), `Search/IncludeSchema`, `Search/AggregateSchema`, `Search/InputSchema` (scopes and instructions), `Search/TextSearchType` (`search.text`, `search.trashed` and the `@relevance` sort), `Search/EngineTextType` (`search.text` of a `ScoutEngine` resource), `Mutate/MutationSchema` (the body of a mutation), `Mutate/WritableSchema` (the fields that an operation writes), `Mutate/RelationEntrySchema` (the relations of an entry), `Mutate/MutationExample` and `Mutate/RelationExample` (the request example of a mutation), `RuleExample` and `RuleBounds` (an example value that honours the rules of a field), `InputFieldSchema` (an action, instruction or scope field with its rules and default), `Mutate/ResultSchema` (the response of a mutation), `Actions/ActionSchema` (the body of an action), `Actions/ActionExample` (the request example of an action), `Actions/ActionResponseSchema` (the response of an action and the progress of a batch), `Metrics/MetricSchema` (the body and the response of a metric), `DetailsResponseType` (the details response), `DetailsEntryTypes` (its entries) and `DetailsParameter` (its `with` parameter)
- Generator stubs — `src/Console/stubs/*.stub`
