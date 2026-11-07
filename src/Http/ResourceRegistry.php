<?php

namespace Lomkit\Rest\Http;

use Illuminate\Contracts\Container\Container;
use Lomkit\Rest\Exceptions\InvalidResourceException;

class ResourceRegistry
{
    /** @var array<class-string<Resource>, Resource> */
    private array $resources = [];

    public function __construct(private readonly Container $container) {}

    /** @param class-string<Resource> $class */
    public function get(string $class): Resource
    {
        if (isset($this->resources[$class])) {
            return $this->resources[$class];
        }

        $resource = $this->container->make($class);

        if (! $resource instanceof Resource) {
            throw InvalidResourceException::notAResource($class, $resource);
        }

        return $this->resources[$class] = $resource;
    }
}
