<?php

namespace Lomkit\Rest\Query;

use Lomkit\Rest\Exceptions\InvalidTextSearchException;
use Lomkit\Rest\Extensions\Contexts\EngineRestriction;
use Lomkit\Rest\Extensions\Contexts\EngineRestrictionContext;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\Kinds\RestrictsEngineQueries;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Text\Engine\EngineQuery;
use Lomkit\Rest\Text\Engine\Profiles\ComparesColumns;
use Lomkit\Rest\Text\Engine\Profiles\FiltersWithGroups;
use Lomkit\Rest\Text\Engine\Profiles\MatchesStringLists;
use Lomkit\Rest\Text\Engine\RestrictingExtension;

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

        return new self($resource, $rows, $engine);
    }

    public function applyTo(EngineQuery $query): void
    {
        $inSql = $this->rows !== null && $query->restrictRows($this->rows) ? $this->rows : null;

        if ($this->rows !== null && $inSql === null) {
            $this->ensureReached($query);
        }

        if ($this->engine === []) {
            return;
        }

        $user = $this->resource->context()->actingUser()->user;
        $registry = $this->resource->context()->extensions();
        $lists = $query->profile() instanceof MatchesStringLists;
        $columns = $query->profile() instanceof ComparesColumns;
        $groups = $query->profile() instanceof FiltersWithGroups;
        $engine = $query->engine();

        foreach ($this->engine as $name => $extension) {
            $restriction = new EngineRestriction($query, new RestrictingExtension($name, $registry->classOf($name)), $this->resource::class);

            $extension->restrictEngine(new EngineRestrictionContext($restriction, $this->resource, $user, $lists, $inSql?->restricts($name) === true, $columns, $engine, $groups));
        }
    }

    private function ensureReached(EngineQuery $query): void
    {
        $unreached = $this->rows?->unreached() ?? [];
        $name = array_key_first($unreached);

        if ($name !== null) {
            throw InvalidTextSearchException::unrestrictedEngine($this->resource::class, $name, $this->resource->context()->extensions()->classOf($name), $query->profile()->name());
        }
    }
}
