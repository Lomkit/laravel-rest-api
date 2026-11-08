<p align="center"><img src="https://raw.githubusercontent.com/Lomkit/art/main/laravel-rest-api/cover.png" alt="Laravel Rest Api: one resource class, a complete REST API for your Eloquent models"></p>

<p align="center">
<a href="https://github.com/Lomkit/laravel-rest-api/actions/workflows/tests.yml"><img src="https://github.com/Lomkit/laravel-rest-api/actions/workflows/tests.yml/badge.svg?branch=3.x" alt="Tests"></a>
<a href="https://github.com/Lomkit/laravel-rest-api/actions/workflows/code-quality.yml"><img src="https://github.com/Lomkit/laravel-rest-api/actions/workflows/code-quality.yml/badge.svg?branch=3.x" alt="Code quality"></a>
<a href="https://packagist.org/packages/lomkit/laravel-rest-api"><img src="https://img.shields.io/packagist/v/lomkit/laravel-rest-api" alt="Latest version"></a>
<a href="https://packagist.org/packages/lomkit/laravel-rest-api"><img src="https://img.shields.io/packagist/dt/lomkit/laravel-rest-api" alt="Total downloads"></a>
<a href="LICENSE"><img src="https://img.shields.io/packagist/l/lomkit/laravel-rest-api" alt="License"></a>
</p>

# Laravel Rest Api

**Describe a model once. Get a complete API.**

Write one resource class for an Eloquent model. Laravel Rest Api turns it into search, mutate, action, metric, details and delete endpoints. Your policies authorize every request, your frontend gets a rich query language, and Scramble gets a typed OpenAPI document.

No controller methods to write, no query parsing to maintain.

- **One class, every endpoint**: search, batch writes, custom actions, metrics, soft deletes.
- **A search grammar** for filters, nested groups, relations, scopes, includes, aggregates and text search.
- **Laravel policies everywhere**: every resource a request reaches, every row it writes.
- **Built for frontends**: a `details` endpoint, per-row abilities, Precognition, precise `422` messages.
- **Documented for you**: an OpenAPI document with typed schemas and examples, through Scramble.

## Requirements

PHP 8.3+ and Laravel 13.30+.

## From model to API

Take a `Show` model with a `stage()` relation and an `artists()` relation whose pivot holds a `role`.

**1. Install the package.**

```bash
composer require lomkit/laravel-rest-api
```

**2. Describe what the API exposes.** Only what you declare exists for the client. `php artisan rest:resource ShowResource --model=Show` scaffolds the class, and `StageResource` and `ArtistResource` follow the same pattern.

```php
namespace App\Rest\Resources;

use App\Models\Show;
use Lomkit\Rest\Fields\Date;
use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Relations\BelongsTo;
use Lomkit\Rest\Relations\BelongsToMany;

class ShowResource extends Resource
{
    public static string $model = Show::class;

    public function fields(): array
    {
        return [
            ID::make('id'),
            Text::make('title')->rules('max:80')->creationRules('required'),
            Text::make('status'),
            Integer::make('seats')->rules('min:0'),
            Date::make('starts_on')->creationRules('required'),
        ];
    }

    public function relations(): array
    {
        return [
            BelongsTo::make('stage', StageResource::class),
            BelongsToMany::make('artists', ArtistResource::class)
                ->pivotFields([Text::make('role')->nullable()]),
        ];
    }
}
```

**3. Bind it to a route.** The controller is one line, and your `ShowPolicy` authorizes every request (`php artisan rest:policy ShowPolicy --model=Show` writes a starting point).

```php
class ShowsController extends Controller
{
    public static string $resource = ShowResource::class;
}
```

```php
// routes/api.php
use App\Rest\Controllers\ShowsController;
use Lomkit\Rest\Facades\Rest;

Rest::resource('shows', ShowsController::class)->middleware('auth:sanctum');
```

**4. Ask.**

```http
POST /api/shows/search
```

```json
{
  "search": {
    "filters": [{"field": "seats", "operator": ">=", "value": 500}],
    "sorts": [{"field": "starts_on"}],
    "includes": [{"relation": "stage"}],
    "gates": ["update"]
  }
}
```

```json
{
  "current_page": 1,
  "data": [
    {
      "id": 2,
      "title": "Opening night",
      "status": "planned",
      "seats": 1200,
      "starts_on": "2026-07-03",
      "gates": {"authorized_to_update": {"allowed": true, "message": null}},
      "stage": {"id": 1, "name": "Main stage"}
    },
    {
      "id": 3,
      "title": "Late night jazz",
      "status": "sold_out",
      "seats": 650,
      "starts_on": "2026-07-04",
      "gates": {"authorized_to_update": {"allowed": true, "message": null}},
      "stage": {"id": 1, "name": "Main stage"}
    }
  ],
  "from": 1,
  "last_page": 1,
  "per_page": 50,
  "to": 2,
  "total": 2,
  "meta": {}
}
```

Each row says whether the user may update it, so the frontend knows which buttons to show.

> **In a hurry?** `php artisan rest:quick-start` asks which models to expose, then writes their resources (fields and relations read from the tables), controllers, starter policies and routes.

## One resource, every endpoint

`Rest::resource('shows', ShowsController::class)` registers:

| Route | Endpoint | What it does |
| --- | --- | --- |
| `GET /api/shows` | details | Describes the resource for the current user: fields, rules, relations, actions, metrics, abilities |
| `POST /api/shows/search` | search | Filters, sorts, includes, aggregates, text search, pagination |
| `POST /api/shows/mutate` | mutate | Creates and updates rows and their relations, in one transaction |
| `POST /api/shows/actions/{action}` | operate | Runs a custom action on the rows a search matches, on listed ids, or on none |
| `POST /api/shows/metrics/{metric}` | metric | Computes a value, trend, partition, progress or table |
| `DELETE /api/shows` | destroy | Deletes rows (soft deletes when the model uses them) |
| `POST /api/shows/restore` | restore | Restores soft deleted rows, with `->withSoftDeletes()` |
| `DELETE /api/shows/force` | forceDestroy | Deletes rows for good, with `->withSoftDeletes()` |
| `GET /api/shows/actions/{action}/batches/{batch}` | batch | Progress of a queued batch, with `->withBatchProgress()` |

`only()`, `except()`, `names()`, `middleware()` and `middlewareFor()` shape the routes like any Laravel route.

## Search: ask for exactly what the screen needs

One body combines text search, scopes, nested filters, filters through relations, aggregates and the sorts on them, includes and selects.

```json
{
  "search": {
    "text": {"value": "night"},
    "scopes": [{"name": "upcoming"}],
    "filters": [
      {"field": "seats", "operator": ">=", "value": 500},
      {"nested": [
        {"field": "stage.name", "value": "Main stage"},
        {"field": "artists.name", "operator": "like", "value": "The%", "type": "or"}
      ]}
    ],
    "aggregates": [{"relation": "artists", "type": "count"}],
    "sorts": [{"field": "artists_count", "direction": "desc"}],
    "selects": [{"field": "title"}],
    "includes": [{"relation": "artists", "selects": [{"field": "name"}]}]
  }
}
```

<details>
<summary>The response</summary>

```json
{
  "current_page": 1,
  "data": [
    {
      "title": "Late night jazz",
      "artists_count": 3,
      "artists": [
        {"name": "Ada Moon", "pivot": {"role": "support"}},
        {"name": "Kofi Brass", "pivot": {"role": "headliner"}},
        {"name": "Nina Volt", "pivot": {"role": "guest"}}
      ],
      "@more": []
    },
    {
      "title": "Opening night",
      "artists_count": 2,
      "artists": [
        {"name": "The Lumens", "pivot": {"role": "support"}},
        {"name": "Nina Volt", "pivot": {"role": "headliner"}}
      ],
      "@more": []
    }
  ],
  "from": 1,
  "last_page": 1,
  "per_page": 50,
  "to": 2,
  "total": 2,
  "meta": {}
}
```

`@more` lists the includes that hold more rows than they returned.
</details>

This search uses two more declarations on the resource: `Scope::make('upcoming')` in `scopes()`, and `Contains::make()` in `text()` with `->searchable()` on the `title` field.

- **Filters** take the operators of their field type, `and`/`or`, nested groups, dotted paths through relations (`stage.name`) and relation groups (`{"relation": "artists", "exists": false}`).
- **Includes** take their own filters, sorts, selects, aggregates, limit and includes, and an `alias` to load one relation twice.
- **Text search** runs on `LIKE` (`Contains`), the full-text index of MySQL, MariaDB or PostgreSQL (`FullText`), or a Laravel Scout engine such as Meilisearch, Typesense or Algolia (`ScoutEngine`).
- **Instructions** add your own query logic, with typed and validated fields.
- **Outside HTTP**, `Rest::search(ShowResource::class)->as($user)->payload($saved)->lazy()` replays a saved search in a job or an export, with the same validation and policies.

## Mutate: write a whole graph in one request

```json
{
  "mutate": [
    {
      "operation": "create",
      "attributes": {"title": "Closing night", "seats": 800, "starts_on": "2026-07-05"},
      "relations": {
        "stage": {"operation": "attach", "key": 1},
        "artists": [
          {"operation": "attach", "key": 4, "pivot": {"role": "headliner"}},
          {"operation": "create", "attributes": {"name": "Echo Park"}}
        ]
      }
    }
  ]
}
```

```json
{"operations": [{"operation": "create", "key": 4, "relations": {"stage": {"operation": "attach", "key": 1}, "artists": [{"operation": "attach", "key": 4}, {"operation": "create", "key": 5}]}}]}
```

Every entry is validated by the rules of its fields and checked against the policies before the first write, then everything runs in one transaction. Relation entries also take `detach`, `sync` and `toggle` on many-to-many relations, and `delete` on child rows. Add a `response` block to get the written rows back, and `versioned()` on the resource to answer `409` when a row changed since the client read it.

A mistake comes back on its exact key. Here, an entry without `title`, with `"seats": -3` and a `created_at`:

```json
{
  "message": "The mutate.0.attributes.title field is required. (and 2 more errors)",
  "errors": {
    "mutate.0.attributes.title": ["The mutate.0.attributes.title field is required."],
    "mutate.0.attributes.seats": ["The mutate.0.attributes.seats field must be at least 0."],
    "mutate.0.attributes": ["The created_at key of the mutate.0.attributes field is not writable on create."]
  }
}
```

## Actions: your business operations, as endpoints

```php
class CancelShows extends Action
{
    public function fields(): array
    {
        return [Text::make('reason')->rules('required')];
    }

    public function handle(ActionFields $fields, Collection $models): void
    {
        foreach ($models as $show) {
            $show->update(['status' => 'cancelled']);
        }
    }

    public function respond(ActionFields $fields, int $impacted): ?ActionResponse
    {
        return ActionResponse::message("{$impacted} shows cancelled: {$fields->reason}");
    }
}
```

Declare it in the resource's `actions()`, and it runs on the rows of a search:

```http
POST /api/shows/actions/cancel-shows
```

```json
{
  "fields": [{"name": "reason", "value": "Storm warning"}],
  "search": {"filters": [{"field": "stage.name", "value": "Main stage"}]}
}
```

```json
{"data": {"impacted": 2, "result": {"type": "message", "message": "2 shows cancelled: Storm warning"}}}
```

Implement `ShouldQueue` to process the rows in queued chunks, and `BatchableAction` to run them as a batch whose progress the client can follow. An action can also target listed ids or no row at all, check a policy ability on each row, and ask the user to confirm before it runs.

## Metrics: dashboards without extra endpoints

```php
class ShowsByStatus extends Partition
{
    public function calculate(Builder $query): PartitionResult
    {
        return $this->count($query, 'status');
    }
}
```

```json
{"data": {"type": "partition", "parts": [{"key": "planned", "label": "planned", "value": 2}, {"key": "sold_out", "label": "sold_out", "value": 1}], "other": null}}
```

`Value`, `Trend`, `Partition`, `Progress` and `Table` cover counts, sums, averages, medians and percentiles, with date ranges, time zones, the previous period and a result cache. A metric computes over the rows the client is allowed to see, and accepts the filters of a search.

## And everything around it

- **Authorization**: your policies run `viewAny` on every resource a request reaches, before validation, and `create`, `update`, `delete`, `restore` or `forceDelete` on every row it writes, before the first write. `perimeterQuery()` scopes each resource to the rows a user may reach (a tenant, a team).
- **OpenAPI documentation**: install [Scramble](https://scramble.dedoc.co) and `/docs/api` documents every endpoint, with a typed body per resource, request examples and error responses.
- **Details for frontends**: `GET /api/shows?with=fields.filter,fields.rules,actions.authorized` tells a frontend how each field filters, which rules each form field follows, and which actions the user may run.
- **Precognition**: live form validation with Laravel Precognition on every endpoint, once `rest.precognition.enabled` is on.
- **Predictable limits**: list sizes, nesting depths and totals are bounded in `config/rest.php`, and an oversized request answers one clear `422`.
- **Generators**: `rest:resource --generate` reads the table, and `rest:controller`, `rest:policy`, `rest:action`, `rest:instruction`, `rest:metric` and `rest:response` scaffold the rest.
- **Laravel Boost**: the package ships a Boost skill with the full feature reference.

## Documentation

Everything else lives on the [documentation site](https://laravel-rest-api.lomkit.com). Coming from 2.x? Read the [upgrade guide](UPGRADE.md).

## Contributing

Contributions are welcome. Read the [contributing guide](CONTRIBUTING.md) first.

## License

Laravel Rest Api is open-source software licensed under the [MIT license](LICENSE).
