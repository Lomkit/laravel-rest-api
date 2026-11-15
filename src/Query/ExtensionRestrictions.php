<?php

namespace Lomkit\Rest\Query;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Extensions\Contexts\RestrictionContext;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\Kinds\RestrictsEngineQueries;
use Lomkit\Rest\Extensions\Kinds\RestrictsQueries;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Text\Engine\RowRestriction;

/** @internal */
final readonly class ExtensionRestrictions implements RowRestriction
{
    /** @param non-empty-array<string, Extension&RestrictsQueries> $extensions */
    private function __construct(
        private Resource $resource,
        private array $extensions,
    ) {}

    public static function of(Resource $resource): ?self
    {
        $extensions = $resource->context()->extensions()->applicable($resource::class, RestrictsQueries::class);

        if ($extensions === []) {
            return null;
        }

        return new self($resource, $extensions);
    }

    /** @param Builder<Model> $query */
    #[\Override]
    public function apply(Builder $query): void
    {
        $user = $this->resource->context()->actingUser()->user;

        foreach ($this->extensions as $name => $extension) {
            $query->where(function (Builder $group) use ($name, $extension, $user): void {
                $extension->restrict(new RestrictionContext($group, $this->resource, $user));

                RestrictionGuard::ensureOnlyWheres($group, $name, $this->resource->context()->extensions()->classOf($name), $this->resource);
            });
        }
    }

    /** @return non-empty-list<array{string, string}> */
    public function extensions(): array
    {
        $registry = $this->resource->context()->extensions();

        return array_map(static fn (string $name): array => [$name, $registry->classOf($name)], array_keys($this->extensions));
    }

    /** @return array<string, Extension&RestrictsQueries> */
    public function unreached(): array
    {
        return array_filter($this->extensions, fn (Extension $extension): bool => ! $extension instanceof RestrictsEngineQueries);
    }
}
