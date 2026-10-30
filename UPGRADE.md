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

- `Controller::newResource()` is removed. In a controller action, use `$request->context()->resource(static::resourceClass())`.
- `app(RestRequest::class)` no longer returns the request in progress. It builds a new request. In a resource, read the request from the method parameter or from `$this->context()->request`.
- A package request reads its resource from the class of the route controller, which must extend `Lomkit\Rest\Http\Controllers\Controller`. On another route, such as a closure route, the request throws `Lomkit\Rest\Exceptions\InvalidControllerException`. Before, it failed with a PHP error.
- The query builder methods that only exist on Eloquent (`mutate()`, `mutations()`, `applyMutation()`, `disableDefaultLimit()`) are declared on a new contract, `Lomkit\Rest\Contracts\EloquentQueryBuilder`. If you bind your own `QueryBuilder` implementation, it must implement `EloquentQueryBuilder`. The package resolves `EloquentQueryBuilder` through your `QueryBuilder` binding.

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
- `Resource` defines `__sleep()`, which leaves out the memoized fields, scopes and relations, because they can hold closures. A queued action's resource therefore rebuilds them in the worker, where the request has no user: `fields()`, `scopes()` and `relations()` that depend on the user see a guest there. A resource that overrides `__sleep()` must declare `: array` and merge `parent::__sleep()`.

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
- `Resource::__sleep()` also leaves out the memoized scopes.

Instructions:

- `fields()` returns `Field` objects instead of a map of rules: `['min_views' => ['integer']]` becomes `[Integer::make('min_views')]`. The field rules are the type rules, `nullable` and `rules()`; rules that compare fields, such as `required_if:status,published`, `date_equals:since` or `Rule::date()->after('since')`, compare with the other inputs of the instruction, as described under Validation engine.
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

Meta:

- `Lomkit\Rest\Concerns\Metable` is removed. Actions, instructions and scopes use `Lomkit\Rest\Concerns\HasMeta`: `withMeta([...])` becomes `meta([...])`, which merges, and the `meta()` getter becomes `getMeta()`. The `$meta` property is private.

Details:

- `scopes` is a list of `{name, fields, meta}` instead of a list of names.
- The `fields` of actions, instructions and scopes are input entries `{name, type, nullable, default, rules, meta}`. `Enum` entries add `values`.
- Rules are described as strings: pipe strings are split, `Stringable` rules are cast to string, other rule objects are named by their snake class name (`enum`, `password`), and closures are left out.
- The resource `fields` stay a list of names.

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
- `rest:resource` and `rest:controller` generate the typed properties, and `rest:base-resource` generates the typed query hooks.

Query hooks:

- `searchQuery()`, `mutateQuery()`, `destroyQuery()`, `restoreQuery()` and `forceDestroyQuery()` return `Illuminate\Contracts\Database\Eloquent\Builder`, and `searchScoutQuery()` returns `Laravel\Scout\Builder`. Add the return type to every override, including the five hooks of the base resource that `rest:base-resource` wrote in `app/Rest/Resources/Resource.php`. The parameter keeps the contract type, and the return type may be the Eloquent `Builder` instead of the contract:

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
  - an override declares its return type: `handle(): void`, `handleScout(): void`, `withBatch(): void`, `name(): string`, `uriKey(): string`, `standalone(): static`, `targeted(): static`, `isStandalone(): bool`, `isTargeted(): bool`, `handleRequest(): int`.
- An action's `handle()` returns `void`. Before, a synchronous action that returned `false` stopped the remaining chunks; the chunks now always run.
- `make()` returns `static`. An action, instruction or relation that redefines it must declare `: static`.
- On relations, `requiredOnCreation()`, `prohibitedOnCreation()`, `requiredOnUpdate()` and `prohibitedOnUpdate()` return `static`. A relation that overrides one of them must declare `: static`. Their callbacks receive the request and the related resource: `fn (RestRequest $request, Resource $resource): bool`.
- The public `relationRequiredOnCreationCallback`, `relationProhibitedOnCreationCallback`, `relationRequiredOnUpdateCallback` and `relationProhibitedOnUpdateCallback` properties of relations are removed: the constraints are now private. Set them with `requiredOnCreation()`, `prohibitedOnCreation()`, `requiredOnUpdate()` and `prohibitedOnUpdate()`, and read them with `isRequiredOnCreation()`, `isProhibitedOnCreation()`, `isRequiredOnUpdate()` and `isProhibitedOnUpdate()`.
- `resource(Resource $resource)` returns `static` on actions, instructions and requests.

Other resource methods:

- `newModel(): Model`, `isModelSearchable(): bool`, `relation(): ?Relation`, `getRelations(): array` and `instruction(): ?Instruction`.
- `performDelete()`, `performRestore()` and `performForceDelete()` return `void`.
- `paginate()` returns `Illuminate\Contracts\Pagination\LengthAwarePaginator`, so an override cannot return a simple or a cursor paginator.

Controller endpoints:

- `details()` and `mutate()` return `array`, `operate()` returns `Illuminate\Http\Response`, and `search()`, `destroy()`, `restore()` and `forceDestroy()` return `Lomkit\Rest\Http\Response`.

Requests:

- `authorize()` returns `bool|Illuminate\Auth\Access\Response`, so it can still return `Response::deny()`.
- `rules()`, `destroyRules()`, `forceDestroyRules()`, `restoreRules()`, `operateRules()` and `resolveFields()` return `array`.
- `Lomkit\Rest\Http\Requests\ActionsRequest` is removed. Nothing used it: the operate hooks receive an `OperateRequest`.

Service provider and facade:

- `RestServiceProvider::register()` and `boot()` return `void`, and the `Rest` facade's `getFacadeAccessor()` returns `string`.

### Search

Text search (`search.text`):

- A request runs a text search when `search.text.value` is filled. A blank, `null` or missing value runs the normal search and returns the normal list, and the rest of the payload is validated as a normal search. Before, a blank or `null` value returned an empty page.
- `search.text.value` accepts at most 255 characters.
- `search.text.trashed` needs a filled `search.text.value`. Without one it gives a 422.
- `viewAny` is checked before any search runs, including a text search without hits and a page past the last one.
- With a text search, `searchQuery()` also applies to the loaded page: a row that the index returns outside `searchQuery()` is left out of `data`. The order of the page, `total` and `last_page` stay the index's.
- A resource that overrides `searchQuery()` must declare `searchScoutQuery()` in the same class, or in a subclass of it, to run a text search. Otherwise a text search throws `Lomkit\Rest\Exceptions\InvalidResourceException`, which names the resource. A resource that overrides neither keeps working. Put the same constraints in both hooks, and write them in `searchScoutQuery()` as `where()` conditions on attributes that the index stores and can filter on, not as a `query()` callback:

  ```php
  use Illuminate\Contracts\Database\Eloquent\Builder;
  use Laravel\Scout\Builder as ScoutBuilder;

  public function searchQuery(RestRequest $request, Builder $query): Builder
  {
      return $query->where('team_id', $request->user()->team_id);
  }

  public function searchScoutQuery(RestRequest $request, ScoutBuilder $query): ScoutBuilder
  {
      return $query->where('team_id', $request->user()->team_id);
  }
  ```

- `rest:base-resource` generates `searchScoutQuery()` next to `searchQuery()`. If your base resource (`app/Rest/Resources/Resource.php`) declares `searchQuery()`, add `searchScoutQuery()` to it, otherwise a text search on the resources that extend it throws.
- Includes and aggregates are validated as in a search without text, also next to a text search, because they run in SQL on the loaded page: their filters, sorts, selects and scopes use the fields and operators of the related resource, not its scout fields. An include or an aggregate whose `relation` is missing or not declared gives a 422.
- Actions refuse `search.text` with a 422 under `search.text`: "Text search is not available for actions."

Validation:

- `search.page` must be an integer of at least 1, with or without text. Before, a page below 1 was accepted.
- `search.aggregates.*.type` is required, and `search.aggregates.*.alias` must be a string when it is sent.
- An include or an aggregate whose `relation` is a dotted path with an unknown first relation (`suppliers.country`) gives a 422 under its `relation` key.
- A filter whose `field` is not a string gives a 422 under its `field` key, in `search.filters`, in nested filters and in the filters of includes and aggregates.
- On an action, `search` must be an object (a JSON array of keys and values) when it is sent.
- A name repeated in the `fields` of an action or an instruction gives a 422 under each repeated entry: `fields.{index}.name` for an action, `search.instructions.{index}.fields.{position}.name` for an instruction. Before, the last value won.
- Each entry of `resources` on `destroy`, `restore` and `forceDestroy` must be a key typed like the resource's `ID` field: an integer for integer keys, and a string, a UUID or a ULID for the other key types (a resource without an `ID` field uses the model's key type). A list, an object or `null` gives a 422 under `resources.{index}`. On `restore` and `forceDestroy`, a key of the wrong type (`"abc"` for an integer key) gives a 422 instead of matching no record.
- In `mutate`, an `attributes`, `relations` or `pivot` value that is not an object gives one 422 message under its key.
- The checks of field, scout field, scope, instruction and action field names use a new rule, `Lomkit\Rest\Rules\OneOf`, instead of `Rule::in()`. The message is still `validation.in` ("The selected :attribute is invalid."), but a custom message written for the `in` rule of these attributes no longer applies.

Query builder and requests:

- `disableSecurity()` is removed from `Lomkit\Rest\Query\Builder` and from the `EloquentQueryBuilder` contract. `searchQuery()` applies to every search.
- `RestRequest::isScoutMode()` is removed. Use `$request->context()->isTextSearch()`, which is true when `search.text.value` is filled.
- `Lomkit\Rest\Rules\Search\Search` no longer validates `text`. `SearchRequest` validates it with `SearchText`, and includes and actions prohibit it.
- `Resource::relation()` returns `null` for a dotted path whose first relation is not declared. Before, it failed with an error.
- `Lomkit\Rest\Rules\ArrayWithKey` stops after reporting that a value is not an array.

### Validation engine

Each package request validates its payload with one Laravel validator, in one pass. Before validating, the request compiles its rules against the payload: each node of the rule tree, a `Lomkit\Rest\Rules\RestRule`, becomes rules on explicit keys such as `search.filters.0.field`, and the validator checks those keys.

Custom rules:

- `RestRule` no longer implements Laravel's `ValidationRule` and `ValidatorAwareRule`: `validate()`, `setValidator()` and the `$validator` property are removed. A node only works in the rules of a package request, which compiles it.
- A custom node implements `buildValidationRules(string $attribute, mixed $value): array`, which returns rules keyed under `$attribute` and may hold other nodes. To add rules in another way, override `compile(RuleCompiler $into, string $attribute, mixed $value): void` (`Lomkit\Rest\Validation\RuleCompiler`), which adds `buildValidationRules()` by default.
- A node is compiled with the value at its key, and only when Laravel would have run a rule there: the key is present, is not an empty string, and is not `null` under `nullable`. When the value is not an array, the key gets the `array` rule and nothing under it is compiled.
- `Lomkit\Rest\Rules\Concerns\ValidatesFields` and `Lomkit\Rest\Rules\Operate\OperateFields` are removed. `Lomkit\Rest\Rules\Input\InputFields` validates the fields of actions and instructions and takes a `FieldSet`. Their error keys do not change. A `validation.custom.<name>.<rule>` translation line no longer applies to an input field: key it by the full key, `validation.custom.fields.<name>.<rule>` for an action or `validation.custom.search.instructions.*.fields.<name>.<rule>` for an instruction. The `validation.attributes.<name>` line still names the field.
- Laravel replaces `*` in the parameters of its own dependent rules, such as `required_if`. A rule registered with `Validator::extendDependent()` does not get this replacement on the keys the request expands.
- The rules of action fields, instruction fields and scope parameters run in the request's validator. A rule that names another input of the same action, instruction or scope compares with that input: this covers the field parameters of Laravel's dependent rules (`required_if`, `gte`, `after`, `same`, …), `date_equals` and `in_array`, written as strings or arrays, in `Rule::date()`, `Rule::numeric()`, `Rule::string()` and `Rule::array()` objects, and inside `Rule::when()`.
- Other rule objects and closures on these inputs see the whole request: a `DataAwareRule` receives the whole payload, where each input value sits under `<attribute>.<name>` (`fields.min`), a `ValidatorAwareRule` receives the request's validator, a `Rule::when()` condition receives the whole payload, and the attribute passed to `validate()` or to a closure is the full key, such as `fields.min` or `search.instructions.0.fields.min`.

Requests:

- `withValidator()` and `after()` on a package request run once, and their validator holds every error of the payload, including those of nested filters, includes, instructions and relations.
- `RestRequest` overrides five methods of `FormRequest` and declares their return types: `validationData(): array`, `validationRules(): array`, `createDefaultValidator(ValidationFactory $factory): Validator` (the `Illuminate\Contracts\Validation\Validator` contract), `shouldValidatePrecognitiveAttribute($attribute, $validateOnly): bool` and `shouldFailOnUnknownFields(): bool`. An override must declare the same type, and should call the parent method.
- `validationRules()` returns the compiled rules, and `validationData()` returns the payload with each input value added under `<attribute>.<name>`, such as `fields.status`.
- `rules()` still returns the rule tree, which Laravel cannot run on its own. To adjust the validator, use `withValidator()`, or override `createDefaultValidator()` and call the parent. A request that defines `validator()` builds the validator itself: it must use `validationData()` and `validationRules()`, and its messages then name input fields and expanded keys by their full key, such as `fields.status`.
- A key that the request expands from a wildcard, such as an entry of a to-many relation (`mutate.0.relations.lineItems.0.operation`), is named as written in messages, as Laravel names wildcard keys, unless the request's `attributes()` or a `validation.attributes` translation line names it. `Validator::setImplicitAttributesFormatter()` does not apply to these keys.
- The input values added by `validationData()` also appear in `validated()` and `safe()`, under `fields.<name>` for an action. `resolveFields()` and the values that `handle()` receives do not change.
- The request's `messages()` and `attributes()` now apply to the fields of actions and instructions, under `fields.<name>` and `search.instructions.<i>.fields.<name>`. Without them, such a field is still named by the `validation.attributes.<name>` translation, or else by its name.
- `FormRequest::failOnUnknownFields()` and the `#[FailOnUnknownFields]` attribute do not apply to package requests: `shouldFailOnUnknownFields()` returns `false`, on every Laravel version that has the setting (13.4 and later).
- With Precognition enabled, a name in `Precognition-Validate-Only` also covers the keys under it: `search` validates `search.limit` and `search.filters.0.field`. Before, a payload with invalid nested values could return `204`.

Payloads:

- A rule of a scope parameter that names another field of the same scope, such as `gte:min`, compares with the parameter at that field's position, `search.scopes.<i>.parameters.<p>`. Before, it read the key of that name at the root of the payload.
- An input field with a numeric name, such as `Integer::make('2')`, throws `InvalidFieldException` when the fields are built, because its value is validated under `<attribute>.<name>`, next to the entry positions.
- The `fields` of an action or an instruction must be a list of `{name, value}` entries. An object keyed by input name gives a 422: `The fields field must be a list.`
- An entry key that holds `*`, such as `"filters": {"*": {...}}`, gives a 422 on its container: `The * key is not valid for the search.filters field.`
- A list entry that is not an object, such as `"includes": ["category"]` or `"mutate": ["create"]`, gives a 422 on that entry: `The search.includes.0 field must be an array.` A to-one relation given a list or `null` in `mutate` also gives a 422. Some of these payloads gave a 500 before.
- Under a to-many relation in `mutate`, the errors are listed entry by entry.
- `sync` and `toggle` are accepted on many-to-many relations only: `BelongsToMany`, `MorphToMany` and `MorphedByMany`. On another relation they give a 422 under the `operation` key of the entry, such as `The selected mutate.0.relations.author.operation is invalid.` Send `attach` there instead. A relation lists the operations it accepts in `mutationOperations()`.

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
- Every resource the payload reaches needs `viewAny` on that resource: each level of an include (`team.replies` needs `viewAny` on the teams and on the replies), each level of an aggregate, each level of a filter on a relation, nested filters included, and a filter on a pivot column (`tags.pivot.position` needs `viewAny` on the tags). The same applies to the `search` of an action. A filter on a relation did not check the related resource before, and an include was checked only when the query returned rows.
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
- With `authorizations.strict` set to `true` (`false` by default), a check that the request did not make before validation throws `Lomkit\Rest\Exceptions\UnplannedCheckException` instead. Turn it on in your test suite to find a resource that the request reaches without checking it first, for example through a custom rule node: a node records its checks in `compile()` with `$into->reach(Check::onClass($resource, 'viewAny', $attribute))` (`Lomkit\Rest\Authorization\Check`).
- A `Lomkit\Rest\Query\Builder` used outside a request evaluates `viewAny` when it searches. In strict mode, plan it first: `$resource->context()->authorizer()->plan([Check::onClass($resource, 'viewAny')])`.

Cache:

- The cache is off by default: `authorizations.cache.enabled` is `false`. A published `config/rest.php` keeps its own value, so set it to `false` there unless you want the cache.
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

- `sorts` accepts the sortable fields only. A `Json` field, a field declared `sortable(false)` or `writeOnly()`, and a `virtual()` field without `sortUsing()` give a 422 under `search.sorts.<i>.field`. A field declared `selectable(false)` can still be sorted. The sorts of a text search still accept the `scoutFields()`.
- A field with `sortUsing()` is sorted by its callback instead of its column. The callback receives the Eloquent query of the resource, on an include as well, and the direction, `asc` or `desc`. A `virtual()` or `writeOnly()` field with `sortUsing()` is sortable.

  ```php
  use Illuminate\Contracts\Database\Eloquent\Builder;

  Text::make('full_name')->virtual()
      ->sortUsing(fn (Builder $query, string $direction) => $query
          ->orderBy('last_name', $direction)->orderBy('first_name', $direction)),
  ```

- The other fields are ordered by their column, qualified with the table of the model: `posts.views`.
- `direction` accepts the values of `Lomkit\Rest\Fields\SortDirection`, `asc` and `desc`. A sort without a direction is ascending.
- `Lomkit\Rest\Query\Builder::sort($field, $direction)` is removed. `applySorts()` keeps its signature and applies each sort with a `Lomkit\Rest\Query\Clauses\SortClause`, which calls `Field::sort()`. A sort passed to `search()` or `applySorts()` must name a declared, sortable field of the resource: another field throws `Lomkit\Rest\Exceptions\InvalidFieldException`. `Lomkit\Rest\Query\ScoutBuilder::sort()` does not change.
- `defaultOrderBy()` still orders by the columns it names.

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
- `Lomkit\Rest\Rules\Search\SearchIncludeAlias` is final and receives the resource and the relation name in its constructor: `new SearchIncludeAlias($resource, 'posts')`. `setResource()` and `setRelation()` are removed.

Scramble:

- The search body documents the sortable fields under `sorts` and the selectable fields under `selects`, with the values of `SortDirection` and `AggregateFunction`.

### Framework integration

- `Lomkit\Rest\Http\Controllers\Controller` no longer extends `Illuminate\Routing\Controller`. The inherited `middleware()`, `getMiddleware()`, `callAction()` and `__call()` methods are removed. Replace the `$this->middleware()` calls in your controller constructors with the `Illuminate\Routing\Controllers\HasMiddleware` interface or with the `#[Middleware]` and `#[Authorize]` attributes:
  - Middleware that a base controller declared for all its children, such as the controller that `rest:base-controller` generates, moves to the static `middleware()` method of `HasMiddleware` on that base controller. Every child controller inherits it on every Laravel 13 release. A child controller that needs more middleware overrides `middleware()` and adds its entries to `parent::middleware()`.

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

  - Middleware of a single controller can also use the attributes on that controller. The first Laravel 13 releases read these attributes only on the controller class that the route names, not on its parent classes, and ignore them on a controller that implements `HasMiddleware` directly or through a parent class. To support these releases, put the attributes on each concrete controller and do not combine them with `HasMiddleware`. These releases also ignored the attributes on every controller that extended `Illuminate\Routing\Controller`, which the package controller did before.

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
- The package defaults merge into your published `config/rest.php` at every level. A nested key that your file leaves out, such as `gates.names.authorized_to_restore` or `gates.message.enabled`, now gets its default value. Before, a top-level key such as `gates` replaced all the defaults under it.
- `destroy` checks that the keys in `resources` exist on the connection of the model. Before, it checked them on the default connection.
