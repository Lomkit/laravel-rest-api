<?php

namespace Lomkit\Rest\Http\Routing;

use Lomkit\Rest\Exceptions\InvalidResourceRouteException;

final readonly class RouteNames
{
    /** @param array<string, string> $overrides */
    public function __construct(
        private string $prefix,
        private array $overrides = [],
        private string $group = '',
    ) {}

    public function prefixedBy(string $prefix): self
    {
        return new self(self::filled($prefix), $this->overrides, $this->group);
    }

    public function named(Endpoint $endpoint, string $name): self
    {
        return new self($this->prefix, [...$this->overrides, $endpoint->value => self::filled($name)], $this->group);
    }

    public function inGroup(string $group): self
    {
        return new self($this->prefix, $this->overrides, $group);
    }

    public function for(Endpoint $endpoint): string
    {
        return $this->overrides[$endpoint->value] ?? $this->prefix.'.'.$endpoint->value;
    }

    public function registered(Endpoint $endpoint): string
    {
        return $this->group.$this->for($endpoint);
    }

    private static function filled(string $name): string
    {
        if (trim($name) === '') {
            throw InvalidResourceRouteException::emptyName();
        }

        return $name;
    }
}
