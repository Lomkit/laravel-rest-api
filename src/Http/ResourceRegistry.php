<?php

namespace Lomkit\Rest\Http;

use Illuminate\Contracts\Container\Container;

class ResourceRegistry
{
    /** @var array<class-string<Resource>, Resource> */
    private array $resources = [];

    public function __construct(private readonly Container $container) {}

    /** @param class-string<Resource> $class */
    public function get(string $class): Resource
    {
        return $this->resources[$class] ??= $this->container->make($class);
    }
}
