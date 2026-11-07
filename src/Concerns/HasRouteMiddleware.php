<?php

namespace Lomkit\Rest\Concerns;

trait HasRouteMiddleware
{
    /** @var list<string> */
    public array $middleware = [];

    /** @var list<string> */
    public array $withoutMiddleware = [];

    /** @param string|list<string> $middleware */
    public function middleware(string|array $middleware): static
    {
        $this->middleware = [...$this->middleware, ...(array) $middleware];

        return $this;
    }

    /** @param string|list<string> $middleware */
    public function withoutMiddleware(string|array $middleware): static
    {
        $this->withoutMiddleware = [...$this->withoutMiddleware, ...(array) $middleware];

        return $this;
    }
}
