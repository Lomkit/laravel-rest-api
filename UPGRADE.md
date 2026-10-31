# Upgrade Guide

## Upgrading to 3.0 from 2.x

Version 3.0 is in development on the `3.x` branch. This guide lists each breaking change that is merged into that branch.

### Requirements

- PHP 8.3 or later.
- Laravel 13.30 or later. Applications on Laravel 12 stay on 2.x, and applications on an earlier Laravel 13 release update Laravel first: `composer update laravel/framework`.
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

### Resource definitions

A resource holds no request state. Its context evaluates `fields()`, `scopes()`, `relations()`, `instructions()`, `actions()`, `limits()`, `scoutFields()` and `scoutInstructions()` once per request, when they are first read, and keeps the results until the request ends. The next request evaluates them again, with its own user.

- The `calculatedFields`, `calculatedScopes`, `calculatedRelations`, `calculatedInstructions`, `calculatedActions`, `calculatedLimits`, `calculatedScoutFields` and `calculatedScoutInstructions` properties of resources are removed. Read the declarations with `getFields()`, `getScopes()`, `getRelations()`, `getInstructions()`, `getActions()`, `getLimits()`, `getScoutFields()` and `getScoutInstructions()`. They still take a request, but they evaluate the declarations with the request of the resource's context, whatever request they receive.
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
| json, array cast | `Json::make('metadata')` |

- `getFields()` returns a `Lomkit\Rest\Fields\FieldSet` instead of an array of names. Call `->names()` to get the old list.
- `rest:resource` generates `ID::make()` with the key name of the model, `ID::make('id')` by default. See [Generators](#generators).
- `Lomkit\Rest\Fields\DateTime` has the same short name as PHP's `\DateTime`. In a file that uses both, alias one of them.

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
- `rest:action` and `rest:instruction` generate a `fields()` method that returns `[]`. Before, it declared a required `id` field that the action or the instruction did not use. See [Generators](#generators).

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
- `rest:resource` and `rest:controller` generate the typed properties.

Query hooks:

- `searchQuery()`, `mutateQuery()`, `destroyQuery()`, `restoreQuery()` and `forceDestroyQuery()` return `Illuminate\Contracts\Database\Eloquent\Builder`, and `searchScoutQuery()` returns `Laravel\Scout\Builder`. Add the return type to every override, including the five hooks that `rest:base-resource` wrote in `app/Rest/Resources/Resource.php` under 2.x. A hook that only returns `$query` can be deleted instead: `rest:base-resource` no longer generates them. The parameter keeps the contract type, and the return type may be the Eloquent `Builder` instead of the contract:

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

- If your base resource (`app/Rest/Resources/Resource.php`) declares `searchQuery()`, add `searchScoutQuery()` to it, otherwise a text search on the resources that extend it throws. The base resource that `rest:base-resource` wrote under 2.x declares a `searchQuery()` that only returns `$query`: delete it, or add `searchScoutQuery()`. `rest:base-resource` now generates no query hook.
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
- `FormRequest::failOnUnknownFields()` and the `#[FailOnUnknownFields]` attribute do not apply to package requests: `shouldFailOnUnknownFields()` returns `false`.
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

  Text::make('full_name')
      ->virtual()
      ->sortUsing(fn (Builder $query, string $direction) => $query
          ->orderBy('last_name', $direction)
          ->orderBy('first_name', $direction)),
  ```

- The other fields are ordered by their column, qualified with the table of the model: `posts.views`.
- `direction` accepts the values of `Lomkit\Rest\Fields\SortDirection`, `asc` and `desc`. A sort without a direction is ascending.
- `Lomkit\Rest\Query\Builder::sort($field, $direction)` is removed. `applySorts()` keeps its signature and applies each sort with a `Lomkit\Rest\Query\Clauses\SortClause`, which calls `Field::sort()`. A sort passed to `search()` or `applySorts()` must name a declared, sortable field of the resource: another field throws `Lomkit\Rest\Exceptions\InvalidFieldException`. `Lomkit\Rest\Query\ScoutBuilder::sort()` does not change.
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
- `Lomkit\Rest\Rules\Search\SearchIncludeAlias` is final and receives the resource and the relation name in its constructor: `new SearchIncludeAlias($resource, 'posts')`. `setResource()` and `setRelation()` are removed.

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

Text search:

- With a text search, `search.filters` still accept the `scoutFields()` with `=`, `in` and `not in`, without `nested` or `type`. The filters of includes and aggregates follow the rules above.

Database differences:

- `like` and `not like` follow the database: they are case-sensitive on PostgreSQL, follow the collation of the column on MySQL (case-insensitive on the default `_ci` collations), and ignore the case of ASCII letters only on SQLite.
- `ilike` and `not ilike` ignore case. PostgreSQL runs `ilike`, and MySQL and SQLite run `like`: on MySQL, `ilike` stays case-sensitive on a column with a binary or case-sensitive collation, and on SQLite it ignores the case of ASCII letters only.

Query builder and custom relations:

- `Lomkit\Rest\Query\Builder::filter()` is removed. `applyFilters(array $filters)` keeps its signature and applies the filters as one group of clauses (`Lomkit\Rest\Query\Clauses\FilterGroupClause`, with a `FieldConditionClause` or a `RelationConditionClause` per filter), which call `Field::filter()`. A filter passed to `search()` or `applyFilters()` must name a filterable field, or a path to one, with an operator of that field: another field or operator throws `Lomkit\Rest\Exceptions\InvalidFieldException`. `Lomkit\Rest\Query\ScoutBuilder::filter()` does not change.
- `Relation::filter()` and the `Lomkit\Rest\Contracts\RelationResource` interface are removed. Remove `implements RelationResource` from your custom relations: an override of `filter()` is no longer called, and a filter through the relation uses `has()` on the Eloquent relation of the same name.
- `Resource::isNestedField()` and the `Lomkit\Rest\Rules\Resource\ResourceFieldOrNested` rule are removed. `Lomkit\Rest\Fields\FieldPath::resolve($resource, 'author.first_name')` returns the relations, the field and the resource of a filter path, or `null` when the path does not reach a filterable field.
- The `relation_without_pivot()` helper is removed, and the package no longer autoloads a file of global functions.

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
- An attribute that the operation cannot write gives a 422 under the `attributes` key of the entry, as an undeclared attribute did. The message changes for both: `The created_at attribute is not writable on create.` On `sync` and `toggle`, it ends with `on update.` Precognition responses change the same way.
- The model is filled with the writable fields of the operation only, field by field, after the `mutating()` hook.

Rules:

- The rules of each writable field validate its attribute: the base rule of its type, `nullable`, `rules()`, and `creationRules()` on `create` or `updateRules()` on `update`, `sync` and `toggle`. The base rule now applies to mutations: `null` is refused unless the field is `nullable()`, and an array is refused except on a `Json` field. For example, a `Text` field needs a string, an `Integer` field an integer, an `Enum` field a value of its enum, and a `Date` or `DateTime` field a date.
- The resource rules still apply, for rules that compare attributes and for nested keys such as `metadata.source`. Each key gets one list: the field rules, then `createRules()` or `updateRules()`, then `rules()`. Pipe strings are split before the merge, so `'string|min:3'` in `rules()` and `'required'` in `createRules()` give `['required', 'string', 'min:3']`. Before, the resource rules were merged with `array_merge_recursive()`, which left a pipe string unsplit next to another rule of the same key.
- A rule that names another attribute, such as `required_if:featured,true` or `after_or_equal:published_on`, compares with that attribute in the same entry, `mutate.0.attributes.featured`, when the resource declares a field of that name. Before, it read the key of that name at the root of the payload.

Nested writes:

- The keys that the parent relation sets win over the attributes of the same name. On `HasOne`, `HasOneOfMany`, `HasMany`, `MorphOne`, `MorphOneOfMany` and `MorphMany`, the entry gets the key of the parent, and on the morph relations its morph type, also when the entry sends its own `BelongsTo` or `MorphTo` relation to another parent. On `BelongsTo` and `MorphTo`, the foreign key and the morph type that the relation sets win over the attributes of the entry. They are read after the `mutating()` hook, so a key that the hook sets on such a relation, for example with `associate()`, is the one written. Before, an attribute such as `category_id` replaced them.
- `MorphOne`, `MorphOneOfMany` and `MorphMany` write the morph type with `getMorphClass()`: with a morph map, they write the alias instead of the class name, as `MorphTo` does.

Custom relations and query builders:

- `Lomkit\Rest\Fields\MutationOperation` lists the operations: `create`, `update`, `attach`, `detach`, `sync` and `toggle`. `Relation::mutationOperations()` returns a list of its cases instead of strings, for example `[MutationOperation::Create, MutationOperation::Attach]`.
- `mutateModel(Model $model, array $attributes, array $mutation, array $owned = [])` takes a fourth parameter, which an override must declare too. `$attributes` holds the attributes of the entry and is written through the writable fields: a key that is not a writable field is not written. `$owned` holds the keys that relations set, written last.
- In `applyMutation($mutation, $attributes, $key)` and `mutations($mutation, $attributes)`, `$attributes` holds the keys that the parent relation sets. They are written after the attributes of the entry.
- The mutation methods of `Lomkit\Rest\Query\Builder` declare native types, and the `EloquentQueryBuilder` contract declares the same: `mutate(array $parameters = []): array`, `applyMutation(array $mutation = [], array $attributes = [], int|string|null $key = null): Model`, `mutations(array $mutation = [], array $attributes = []): array` and `mutateModel(Model $model, array $attributes, array $mutation, array $owned = []): Model`; `mutationAbility(array $mutation): string` is protected and `$mutateOperationsVerbose` is a typed `array`. An override or an implementation of the contract must declare compatible types.
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
- A pivot field is a column of the pivot table, and a pivot value is written to its column as sent. A pivot field declared `virtual()`, or with `fillUsing()`, throws `Lomkit\Rest\Exceptions\InvalidFieldException` when `pivotFields()` is called.
- `getPivotFields()` returns a `Lomkit\Rest\Fields\FieldSet` instead of an array of names. Call `->names()` to get the old list. `getPivotRules()` and the `$pivotFields` and `$pivotRules` properties are removed.
- The pivot fields are bound to the pivot class of the Eloquent relation: the class given to `using()`, or Laravel's `Pivot`. An `Enum` pivot field reads its enum class from the casts of that class.
- A custom relation that carries pivot fields implements `Lomkit\Rest\Contracts\PivotRelation`: `getPivotFields(): FieldSet` and `pivotRules(string $prefix): array`. The `Lomkit\Rest\Concerns\Relations\HasPivotFields` trait implements both. The package checks this interface instead of the trait.
- `BelongsToMany`, `MorphToMany` and `MorphedByMany` no longer override `rules()`. `Relation::rules()` returns the pivot rules of a `PivotRelation`: a `Lomkit\Rest\Rules\Mutate\MutatePivot` node on each entry of the relation.

Writes:

- `pivot` accepts only the pivot fields that the operation of its entry can write. `create` and `attach` add a pivot row, so they accept the creatable pivot fields. `update`, `sync` and `toggle` accept the updatable pivot fields. `detach` still refuses `pivot`. `Lomkit\Rest\Fields\MutationOperation::pivotWriteOperation()` maps the operations. A pivot field declared `readonly()` is refused on every operation, and a `writeOnly()` pivot field is accepted.
- A pivot key that the operation cannot write gives a 422 under the `pivot` key of the entry, as an undeclared pivot key does, with a new message for both: `The created_at attribute is not writable on create.` On `update`, `sync` and `toggle`, it ends with `on update.` A `pivot` that is not an object gives `The mutate.0.relations.tags.0.pivot field must be an array.`
- The rules of each writable pivot field validate its value under `mutate.0.relations.tags.0.pivot.weight`: the base rule of its type, `nullable`, `rules()`, and `creationRules()` on `create` and `attach` or `updateRules()` on `update`, `sync` and `toggle`. `null` is refused unless the field is `nullable()`. The rules run when `pivot` is missing too, so a pivot field with `creationRules('required')` is required on `attach`. Each entry is validated with its own operation.
- A rule that names another pivot field, such as `required_with:note`, compares with that key in the pivot of the same entry.
- The relation writes the writable pivot fields of the operation only, also when it is called without validation.
- `update`, `sync` and `toggle` validate the pivot with the updatable fields and `updateRules()`, also when they add a pivot row: validation runs before the database is read, so it cannot know whether the row exists. A `creationRules('required')` pivot field is not required then. Use `attach` to add a row with the creation rules.

Reads:

- The `pivot` of an included row holds the selectable pivot fields only. A pivot field declared `writeOnly()` or `selectable(false)` is not returned.
- Filters on `pivot` keys, such as `tags.pivot.weight`, accept the filterable pivot fields only, with the operators and values of their type, as described under Filters. A pivot field declared `writeOnly()` or `filterable(false)` gives a 422 under `search.filters.<i>.field`.

Scramble:

- The relations are read from the relation objects that `relations()` returns. A field declared in `pivotFields()` is no longer documented as a relation. A custom relation is documented with its class name, the operations of its `mutationOperations()`, as a list when it has many entries, and with its pivot when it implements `PivotRelation`.
- The `pivot` of a relation entry lists the writable pivot fields with their rules. Its description names the fields writable on each operation: `create` and `attach`, then `update`, `sync` and `toggle`.

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
- The package defaults merge into your published `config/rest.php` at every level. A nested key that your file leaves out, such as `gates.names.authorized_to_restore` or `gates.message.enabled`, now gets its default value. Before, a top-level key such as `gates` replaced all the defaults under it.
- `destroy` checks that the keys in `resources` exist on the connection of the model. Before, it checked them on the default connection.

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
3. A cast that a field type matches gives that type: a backed enum gives `Enum`, the JSON casts (`array`, `json`, `object`, `collection`, their encrypted forms and the `As*` array and collection casts) give `Json`, and the `boolean`, `integer`, `decimal`, `float`, `date` and `datetime` casts give `Boolean`, `Integer`, `Number`, `Date` and `DateTime`.
4. Otherwise the column type gives the field type: `Integer`, `Number`, `Boolean` (also `tinyint(1)`), `Date`, `DateTime`, or `Text` for the string, text, `uuid`, `enum`, `time`, `json` and `jsonb` types. Another type, such as a binary column, gives `Text` with a note. A `json` or `jsonb` column without a JSON cast also gets a note: Eloquent encodes an array only for a cast attribute, so the client sends the JSON as a string. Cast the column to `array`, on the model or on the pivot model, to get `Json`. On SQLite, a `json` column is a text column.
5. A nullable column gets `nullable()`.
6. The timestamps of the model (`created_at`, `updated_at`, and `deleted_at` with `SoftDeletes`), the auto-increment and generated columns, the columns that `HasUuids` or `HasUlids` fill, and the foreign key of a generated `BelongsTo` relation get `readonly()`, and nothing else.
7. A hidden column gets `writeOnly()`: clients may write it, and it is never returned. The package does not read `$hidden` at runtime: the generated line declares it.
8. A `Text` or `ID` field whose column reports a length gets `rules('max:<length>')`. MySQL and PostgreSQL report it, SQLite does not. A `Text` field of a `json` or `jsonb` column gets `rules('json')`.
9. A column that is not nullable and has no default gets `creationRules('required')`, and a column with a unique index of its own gets `Rule::unique(<Model>::class, '<column>')` in its creation rules.

A field with more than one call after `make()` is written one call per line.

With `--generate`, the relations of the model are generated through these rules:

- The command reads the public methods of the model whose declared return type is an Eloquent relation, such as `public function author(): BelongsTo`, and calls them on a new instance. It never calls a method without a declared relation return type, a static method or a method with a required parameter. The relations keep the order of the methods in the model.
- A relation is generated when its related model has a resource: a concrete resource under `app/Rest/Resources` whose `$model` is that model, or the resource being generated, for a relation of the model to itself. When several resources have that model, the resource being generated comes first, then the first class name in alphabetical order. `BelongsTo`, `HasOne`, `HasMany`, `BelongsToMany`, `MorphOne`, `MorphMany`, `MorphToMany`, `HasOneThrough` and `HasManyThrough` give the relation class of the same name, for example `BelongsTo::make('author', UserResource::class)`, and `morphedByMany()` gives `MorphedByMany`. A resource in another namespace is imported.
- A relation whose related model has no resource is not generated, and nothing is exposed for it: the command prints `Skipped: author: no resource for App\Models\User. Generate one first with php artisan rest:resource UserResource --model=User --generate, then run this command again with --force.` Its foreign key stays a writable field.
- A `MorphTo` relation, or a relation of another class, is not generated: the command prints `Skipped: commentable: MorphTo relations are not generated, declare it in relations() by hand.`
- A generated `BelongsTo` relation makes its foreign key `readonly()`, so clients set the key through the relation. When the foreign key column is not nullable and has no default, the relation gets `requiredOnCreation()`.
- A `BelongsToMany`, `MorphToMany` or `MorphedByMany` relation whose definition declares `withPivot()` columns or `withTimestamps()` gets `pivotFields([...])`: one field for each of these columns of the pivot table, typed by the rules above from the column and the casts of the pivot model. The pivot timestamps get `readonly()`, and a pivot field gets no `Rule::unique()`. A note names a pivot column as `<relation>.pivot.<column>`.

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
```

- The command asks `Which models should the API expose?` and offers the models of `app/Models`, or of `app` when that directory does not exist, with none selected. With `--no-interaction`, or when no terminal can answer, pass each model with `--model`: without it, the command stops with exit code 1 and writes nothing. Before, it always exposed `App\Models\User`.
- Each model must exist and have a table. Otherwise the command stops with exit code 1 before any file is written.
- For each model, it writes the resource with the fields and relations of the table, as `rest:resource --generate` does, the controller, and a read-only policy, as `rest:policy` does, when Gate finds no policy for the model. It creates the base resource and the base controller when they are missing. The relations between the models of one run are generated. A relation to a model that is not exposed is skipped and reported: `Skipped: Post.author: no resource for App\Models\User. Rerun with --model=User to expose users.`
- When `routes/api.php` does not exist, the command asks before it runs `php artisan install:api`, which installs Laravel Sanctum. `--install-api` runs it without asking. With `--no-interaction` or without a terminal, for example in CI, and without `--install-api`, or when you decline, the command stops with exit code 1 and writes nothing. The command no longer edits `bootstrap/app.php`: `install:api` registers `routes/api.php`.
- It appends one route per model to `routes/api.php`, behind `auth:sanctum`, and adds the `use` imports of the classes it names:

  ```php
  Rest::resource('posts', PostsController::class)
      ->middleware('auth:sanctum');
  ```

  A controller that `routes/api.php` already names, by its full name, an import, a group import, an alias or an imported namespace, is not added again. The imports go after the existing ones, or after `declare(strict_types=1);` when the file has none. Before, the route had no middleware.
- Before it writes anything, the command checks that no route of the application already answers the URIs of the new routes under the prefix of the API routes, or uses their names, such as `posts.destroy` from `Route::apiResource('posts', ...)`. Otherwise it lists these routes and stops with exit code 1: rename or remove them, or leave their models out.
- Then it checks the result in a new PHP process: each controller has routes, each route is behind an authentication middleware whose guards exist, and Gate finds a policy for each model. It prints `checks (fresh process) ... 10 routes authenticated, 2 policies found`. When a check fails, the command lists the problems, the skipped relations and the policy reminder, and stops with exit code 1. The files it wrote stay, and the next run keeps them. Before, the command always exited with 0.
- An existing class is never changed: the command reports it as `kept`. A second run prints `kept` on every line and exits with 0.
- The command never edits the user model. When the user model does not use `Laravel\Sanctum\HasApiTokens`, it prints a warning. It ends with a command that creates a token, a `curl` request to the search route that the application registered for the first model, with its API prefix, and, when it wrote policies, a reminder that they refuse every write.
- The generated classes use the root namespace of the application, such as `MyApp\`. Before, the command replaced `App\` in the generated files, which gave `MyMyApp\`.
