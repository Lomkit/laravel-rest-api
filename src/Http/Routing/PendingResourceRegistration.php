<?php

namespace Lomkit\Rest\Http\Routing;

use Closure;
use Illuminate\Routing\RouteCollection;
use Lomkit\Rest\Exceptions\InvalidResourceRouteException;
use Lomkit\Rest\Http\Controllers\Controller;

final class PendingResourceRegistration
{
    private EndpointSelection $selection;

    private RouteNames $names;

    /** @var array<string, RouteMiddleware> */
    private array $endpointMiddleware = [];

    private bool $settled = false;

    /** @param class-string<Controller> $controller */
    public function __construct(
        private readonly ResourceRegistrar $registrar,
        private readonly UriSegment $name,
        private readonly string $controller,
        private RouteMiddleware $middleware,
    ) {
        $this->selection = new EndpointSelection;
        $this->names = new RouteNames($name->value);
    }

    /** @param Endpoint|string|list<Endpoint|string> ...$endpoints */
    public function only(Endpoint|string|array ...$endpoints): self
    {
        return $this->apply(fn () => $this->selection = $this->selection->only(Endpoint::list(...$endpoints)));
    }

    /** @param Endpoint|string|list<Endpoint|string> ...$endpoints */
    public function except(Endpoint|string|array ...$endpoints): self
    {
        return $this->apply(fn () => $this->selection = $this->selection->except(Endpoint::list(...$endpoints)));
    }

    /** @param Endpoint|string|list<Endpoint|string> ...$endpoints */
    public function withSoftDeletes(Endpoint|string|array ...$endpoints): self
    {
        return $this->apply(function () use ($endpoints): void {
            $softDeletes = Endpoint::list(...$endpoints) ?: Endpoint::optIns();

            foreach ($softDeletes as $endpoint) {
                if (! $endpoint->isOptIn()) {
                    throw InvalidResourceRouteException::notSoftDeletable($endpoint);
                }
            }

            $this->selection = $this->selection->optIn($softDeletes);
        });
    }

    /** @param string|array<string, string> $names */
    public function names(string|array $names): self
    {
        return $this->apply(function () use ($names): void {
            if (is_string($names)) {
                $this->names = $this->names->prefixedBy($names);

                return;
            }

            foreach ($names as $endpoint => $name) {
                $this->names = $this->names->named(Endpoint::fromKey($endpoint), $name);
            }
        });
    }

    public function name(Endpoint|string $endpoint, string $name): self
    {
        return $this->apply(fn () => $this->names = $this->names->named(Endpoint::fromKey($endpoint), $name));
    }

    /** @param string|list<string> $middleware */
    public function middleware(string|array $middleware): self
    {
        $this->middleware = $this->middleware->with((array) $middleware);

        return $this;
    }

    /**
     * @param  Endpoint|string|list<Endpoint|string>  $endpoints
     * @param  string|list<string>  $middleware
     */
    public function middlewareFor(Endpoint|string|array $endpoints, string|array $middleware): self
    {
        return $this->apply(fn () => $this->forEndpoints($endpoints, fn (RouteMiddleware $current) => $current->with((array) $middleware)));
    }

    /** @param string|list<string> $middleware */
    public function withoutMiddleware(string|array $middleware): self
    {
        $this->middleware = $this->middleware->without((array) $middleware);

        return $this;
    }

    /**
     * @param  Endpoint|string|list<Endpoint|string>  $endpoints
     * @param  string|list<string>  $middleware
     */
    public function withoutMiddlewareFor(Endpoint|string|array $endpoints, string|array $middleware): self
    {
        return $this->apply(fn () => $this->forEndpoints($endpoints, fn (RouteMiddleware $current) => $current->without((array) $middleware)));
    }

    public function register(): RouteCollection
    {
        $this->settled = true;

        return $this->registrar->register($this->definitions(ResourceVerbs::current()));
    }

    public function __destruct()
    {
        if (! $this->settled) {
            $this->register();
        }
    }

    /** @return list<RouteDefinition> */
    private function definitions(ResourceVerbs $verbs): array
    {
        return array_map(fn (Endpoint $endpoint): RouteDefinition => new RouteDefinition(
            $endpoint->methods(),
            $endpoint->uri($this->name, $verbs),
            $this->names->for($endpoint),
            $this->controller.'@'.$endpoint->value,
            $this->middleware->merge($this->endpointMiddleware[$endpoint->value] ?? new RouteMiddleware),
        ), $this->selection->resolve());
    }

    /**
     * @param  Endpoint|string|list<Endpoint|string>  $endpoints
     * @param  Closure(RouteMiddleware): RouteMiddleware  $change
     */
    private function forEndpoints(Endpoint|string|array $endpoints, Closure $change): void
    {
        foreach (Endpoint::list($endpoints) as $endpoint) {
            $this->endpointMiddleware[$endpoint->value] = $change($this->endpointMiddleware[$endpoint->value] ?? new RouteMiddleware);
        }
    }

    /** @param Closure(): mixed $change */
    private function apply(Closure $change): self
    {
        try {
            $change();
        } catch (InvalidResourceRouteException $exception) {
            $this->settled = true;

            throw $exception;
        }

        return $this;
    }
}
