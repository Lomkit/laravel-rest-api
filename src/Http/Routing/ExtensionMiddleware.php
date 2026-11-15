<?php

namespace Lomkit\Rest\Http\Routing;

use Lomkit\Rest\Exceptions\InvalidRouteExtensionException;
use Lomkit\Rest\Extensions\Contexts\RouteMiddlewareContext;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\Kinds\AddsRouteMiddleware;
use Lomkit\Rest\Http\Controllers\Controller;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Http\RestContext;

/** @internal */
final readonly class ExtensionMiddleware
{
    /**
     * @param  class-string<Resource>  $resource
     * @param  class-string<Controller>  $controller
     * @param  array<string, Extension&AddsRouteMiddleware>  $extensions
     */
    private function __construct(
        private string $resource,
        private string $controller,
        private array $extensions,
    ) {}

    public static function none(): self
    {
        return new self(Resource::class, Controller::class, []);
    }

    /**
     * @param  class-string<Resource>  $resource
     * @param  class-string<Controller>  $controller
     */
    public static function of(RestContext $context, string $resource, string $controller): self
    {
        $extensions = $context->extensions()->applicable($resource, AddsRouteMiddleware::class);

        if ($extensions === []) {
            return self::none();
        }

        ksort($extensions);

        return new self($resource, $controller, $extensions);
    }

    /** @return list<string> */
    public function for(Endpoint $endpoint, string $routeName): array
    {
        $middleware = [];

        foreach ($this->extensions as $name => $extension) {
            $added = $extension->routeMiddleware(new RouteMiddlewareContext($this->resource, $endpoint, $this->controller, $routeName));

            array_push($middleware, ...$this->checked($name, $extension, $endpoint, $added));
        }

        return array_values(array_unique($middleware));
    }

    /**
     * @param  array<mixed>  $middleware
     * @return list<string>
     */
    private function checked(string $name, Extension $extension, Endpoint $endpoint, array $middleware): array
    {
        $checked = [];

        foreach ($middleware as $item) {
            if (! is_string($item) || trim($item) === '') {
                throw InvalidRouteExtensionException::invalidMiddleware($name, $extension::class, $this->resource, $endpoint, is_string($item) ? 'an empty string' : get_debug_type($item));
            }

            $checked[] = $item;
        }

        return $checked;
    }
}
