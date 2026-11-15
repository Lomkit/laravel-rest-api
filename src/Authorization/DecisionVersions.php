<?php

namespace Lomkit\Rest\Authorization;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Exceptions\InvalidResourceException;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Support\Overrides;

/** @internal */
final class DecisionVersions
{
    /** @var array<class-string<Resource>, bool> */
    private static array $hooked = [];

    public function __construct(private readonly ?Authenticatable $user = null) {}

    public function appliesTo(Resource $resource): bool
    {
        return self::hooked($resource);
    }

    /** @param non-empty-list<Check> $checks */
    public function collect(array $checks, int $since): DependentVersions
    {
        [$resource, $ability] = [$checks[0]->resource, $checks[0]->ability];
        $rows = self::rows($checks);
        $collected = new CollectedVersions($resource::class, $ability, $since, $rows);
        $versions = new RowVersions($resource, $ability, $rows, $this->user, $collected);
        $state = PreparedRows::of($rows, LoadedRelationNames::of($rows));
        $resource->versionAuthorization($versions);

        if (! $state->keeps($rows)) {
            throw InvalidResourceException::changedVersionedRows($resource::class, $ability);
        }

        return $collected->collected();
    }

    private static function hooked(Resource $resource): bool
    {
        return self::$hooked[$resource::class] ??= Overrides::method($resource, 'versionAuthorization', Resource::class);
    }

    /**
     * @param  non-empty-list<Check>  $checks
     * @return Collection<int, Model>
     */
    private static function rows(array $checks): Collection
    {
        $rows = [];

        foreach ($checks as $check) {
            foreach ($check->models() as $model) {
                $rows[spl_object_id($model)] = $model;
            }
        }

        return new Collection(array_values($rows));
    }
}
