<?php

namespace Lomkit\Rest\Http;

use Illuminate\Container\Container as IlluminateContainer;
use Illuminate\Contracts\Container\Container;
use Lomkit\Rest\Http\Requests\RestRequest;

final class RestContext
{
    private readonly ResourceRegistry $resources;

    public function __construct(
        public readonly RestRequest $request,
        Container $container,
    ) {
        $this->resources = new ResourceRegistry($container);
    }

    /** @param class-string<Resource> $class */
    public function resource(string $class): Resource
    {
        return $this->resources->get($class)->withinContext($this);
    }

    public function __serialize(): array
    {
        return [];
    }

    /** @param array<string, mixed> $data */
    public function __unserialize(array $data): void
    {
        $this->__construct(RestRequest::createFrom(request()), IlluminateContainer::getInstance());
    }
}
