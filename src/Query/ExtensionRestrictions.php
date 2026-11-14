<?php

namespace Lomkit\Rest\Query;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Extensions\Contexts\RestrictionContext;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\Kinds\RestrictsQueries;
use Lomkit\Rest\Http\Resource;

/** @internal */
final readonly class ExtensionRestrictions
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

        ksort($extensions);

        return new self($resource, $extensions);
    }

    /** @param Builder<Model> $query */
    public function apply(Builder $query): void
    {
        $user = $this->resource->context()->actingUser()->user;

        foreach ($this->extensions as $name => $extension) {
            $query->where(function (Builder $group) use ($name, $extension, $user): void {
                $extension->restrict(new RestrictionContext($group, $this->resource, $user));

                RestrictionGuard::ensureOnlyWheres($group, $name, $this->resource);
            });
        }
    }

    /** @return list<string> */
    public function names(): array
    {
        return array_keys($this->extensions);
    }
}
