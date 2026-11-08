<?php

namespace Lomkit\Rest\Validation;

use Lomkit\Rest\Events\KeysOutOfReach;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Http\RestContext;
use Lomkit\Rest\Http\Routing\Endpoint;

final class ReachLog
{
    /** @var array<class-string<Resource>, array{Resource, array<string, int|string>}> */
    private array $refused = [];

    public function recorder(Resource $resource): ReachRecorder
    {
        return new ReachRecorder($this, $resource);
    }

    public function refuse(Resource $resource, string $path, int|string $key): void
    {
        $this->refused[$resource::class] ??= [$resource, []];
        $this->refused[$resource::class][1][$path] = $key;
    }

    public function report(RestContext $context, Endpoint $endpoint): void
    {
        if ($this->refused === []) {
            return;
        }

        $acting = $context->actingUser();
        $events = $context->events();

        foreach ($this->refused as [$resource, $refused]) {
            $events->dispatch(KeysOutOfReach::of($resource, $refused, $endpoint, $acting));
        }
    }
}
