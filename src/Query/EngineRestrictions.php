<?php

namespace Lomkit\Rest\Query;

use Lomkit\Rest\Extensions\Contexts\EngineRestriction;
use Lomkit\Rest\Extensions\Contexts\EngineRestrictionContext;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\Kinds\RestrictsEngineQueries;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Text\Engine\EngineQuery;

/** @internal */
final readonly class EngineRestrictions
{
    /** @param non-empty-array<string, Extension&RestrictsEngineQueries> $engine */
    private function __construct(
        private Resource $resource,
        private array $engine,
    ) {}

    public static function of(Resource $resource): ?self
    {
        $engine = $resource->context()->extensions()->applicable($resource::class, RestrictsEngineQueries::class);

        if ($engine === []) {
            return null;
        }

        ksort($engine);

        return new self($resource, $engine);
    }

    public function applyTo(EngineQuery $query): void
    {
        $context = new EngineRestrictionContext(new EngineRestriction($query), $this->resource, $this->resource->context()->actingUser()->user);

        foreach ($this->engine as $extension) {
            $extension->restrictEngine($context);
        }
    }
}
