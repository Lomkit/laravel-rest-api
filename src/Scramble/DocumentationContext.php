<?php

namespace Lomkit\Rest\Scramble;

use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Str;
use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Http\Controllers\Controller;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Http\RestContext;
use Lomkit\Rest\Http\Routing\Endpoint;
use Lomkit\Rest\Metrics\Metric;

final readonly class DocumentationContext
{
    public function __construct(
        public Route $route,
        public Endpoint $endpoint,
        public Resource $resource,
        public string $name,
    ) {}

    /** @param class-string<Controller> $controller */
    public static function guest(Route $route, string $method, Endpoint $endpoint, string $controller, Container $container): self
    {
        $request = RestRequest::createFrom(Request::create('/'.$route->uri(), strtoupper($method)));
        $resource = (new RestContext($request, $container))->resource($controller::resourceClass());

        return new self($route, $endpoint, $resource, str_replace('Resource', '', class_basename($resource)));
    }

    public function declaration(): Action|Metric|null
    {
        $key = $this->declarationKey();

        if ($key === null) {
            return null;
        }

        return match ($this->endpoint) {
            Endpoint::Operate => array_find($this->resource->definition()->actions(), fn (Action $action): bool => $action->uriKey() === $key),
            Endpoint::Metric => $this->resource->definition()->metric($key),
            default => null,
        };
    }

    public function isCatchAll(): bool
    {
        return in_array($this->endpoint, [Endpoint::Operate, Endpoint::Metric], true) && $this->declarationKey() === null;
    }

    public function authenticates(): bool
    {
        $excluded = $this->route->excludedMiddleware();

        return array_any($this->route->gatherMiddleware(), fn (mixed $middleware): bool => is_string($middleware) && ! in_array($middleware, $excluded, true) && Str::is(['auth', 'auth:*'], $middleware));
    }

    private function declarationKey(): ?string
    {
        $parameter = $this->endpoint->parameter();
        $key = $parameter === null ? null : ($this->route->defaults[$parameter] ?? null);

        return is_string($key) ? $key : null;
    }
}
