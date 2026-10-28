<?php

namespace Lomkit\Rest\Http\Routing;

final readonly class RouteDefinition
{
    /**
     * @param  list<string>  $methods
     * @param  array<string, string>  $defaults
     */
    public function __construct(
        public array $methods,
        public string $uri,
        public string $name,
        public string $uses,
        public RouteMiddleware $middleware,
        public array $defaults = [],
    ) {}

    /** @return array{uses: string, as: string, middleware: list<string>, excluded_middleware: list<string>} */
    public function action(): array
    {
        return [
            'uses' => $this->uses,
            'as' => $this->name,
            'middleware' => $this->middleware->included,
            'excluded_middleware' => $this->middleware->excluded,
        ];
    }
}
