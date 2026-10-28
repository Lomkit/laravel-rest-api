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
