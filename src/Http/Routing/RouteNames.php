<?php

namespace Lomkit\Rest\Http\Routing;

use Lomkit\Rest\Exceptions\InvalidResourceRouteException;

final readonly class RouteNames
{
    /** @param array<string, string> $overrides */
    public function __construct(
        private string $prefix,
        private array $overrides = [],
    ) {}

    public function prefixedBy(string $prefix): self
    {
        return new self(self::filled($prefix), $this->overrides);
    }

    public function named(Endpoint $endpoint, string $name): self
    {
        return new self($this->prefix, [...$this->overrides, $endpoint->value => self::filled($name)]);
    }

    public function for(Endpoint $endpoint): string
    {
        return $this->overrides[$endpoint->value] ?? $this->prefix.'.'.$endpoint->value;
    }

    private static function filled(string $name): string
    {
        if (trim($name) === '') {
            throw InvalidResourceRouteException::emptyName();
        }

        return $name;
    }
}
