<?php

namespace Lomkit\Rest\Query;

use Lomkit\Rest\Exceptions\InvalidTextSearchException;
use Lomkit\Rest\Extensions\Contexts\EngineRestriction;
use Lomkit\Rest\Extensions\Contexts\EngineRestrictionContext;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\Kinds\RestrictsEngineQueries;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Text\Engine\EngineQuery;

/** @internal */
final readonly class EngineRestrictions
{
    /** @param array<string, Extension&RestrictsEngineQueries> $engine */
    private function __construct(
        private Resource $resource,
        private ?ExtensionRestrictions $rows,
        private array $engine,
    ) {}

    public static function of(Resource $resource): ?self
    {
        $rows = ExtensionRestrictions::of($resource);
        $engine = $resource->context()->extensions()->applicable($resource::class, RestrictsEngineQueries::class);

        if ($rows === null && $engine === []) {
            return null;
        }

        ksort($engine);

        return new self($resource, $rows, $engine);
    }

    public function applyTo(EngineQuery $query): void
    {
        if ($this->rows !== null && ! $query->restrictRows($this->rows)) {
            $this->ensureReached($query);
        }

        if ($this->engine === []) {
            return;
        }

        $profile = $query->profile();
        $user = $this->resource->context()->actingUser()->user;
        $context = new EngineRestrictionContext(new EngineRestriction($query), $this->resource, $user, $profile->matchesStringLists(), $profile->appliesRowRestrictions());

        foreach ($this->engine as $extension) {
            $extension->restrictEngine($context);
        }
    }

    private function ensureReached(EngineQuery $query): void
    {
        $unreached = $this->rows?->unreached() ?? [];
        $name = array_key_first($unreached);

        if ($name !== null) {
            throw InvalidTextSearchException::unrestrictedEngine($this->resource::class, $name, $unreached[$name]::class, $query->profile()->name());
        }
    }
}
