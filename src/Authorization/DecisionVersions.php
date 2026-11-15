<?php

namespace Lomkit\Rest\Authorization;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Exceptions\InvalidAuthorizationExtensionException;
use Lomkit\Rest\Exceptions\InvalidResourceException;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\ExtensionRegistry;
use Lomkit\Rest\Extensions\Kinds\VersionsRowChecks;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Support\Overrides;

/** @internal */
final class DecisionVersions
{
    /** @var array<class-string<Resource>, bool> */
    private static array $hooked = [];

    /** @var array<class-string<Resource>, array<string, array{class-string<Extension>, Extension&VersionsRowChecks}>> */
    private array $extensions = [];

    /** @param (Closure(): ExtensionRegistry)|null $registry */
    public function __construct(
        private readonly ?Authenticatable $user = null,
        private readonly ?Closure $registry = null,
    ) {}

    public function appliesTo(Resource $resource): bool
    {
        return self::hooked($resource) || $this->extensionsOf($resource) !== [];
    }

    /** @param list<Model> $models */
    public function collect(Resource $resource, string $ability, array $models, int $since): DependentVersions
    {
        $rows = new Collection($models);
        $collected = new CollectedVersions($resource::class, $ability, $since, $rows);
        $versions = new RowVersions($resource, $ability, $rows, $this->user, $collected);
        $names = LoadedRelationNames::of($rows);

        if (self::hooked($resource)) {
            $state = PreparedRows::of($rows, $names);
            $resource->versionAuthorization($versions);

            if (! $state->keeps($rows)) {
                throw InvalidResourceException::changedVersionedRows($resource::class, $ability);
            }
        }

        foreach ($this->extensionsOf($resource) as $name => [$class, $extension]) {
            $state = PreparedRows::of($rows, $names);
            $collected->from($name, $class);
            $extension->versionRowChecks($versions);

            if (! $state->keeps($rows)) {
                throw InvalidAuthorizationExtensionException::changedVersionedRows($name, $class, $resource::class, $ability);
            }
        }

        return $collected->collected();
    }

    /** @return array<string, array{class-string<Extension>, Extension&VersionsRowChecks}> */
    private function extensionsOf(Resource $resource): array
    {
        if ($this->registry === null) {
            return [];
        }

        return $this->extensions[$resource::class] ??= self::applicable(($this->registry)(), $resource);
    }

    /** @return array<string, array{class-string<Extension>, Extension&VersionsRowChecks}> */
    private static function applicable(ExtensionRegistry $registry, Resource $resource): array
    {
        $extensions = [];

        foreach ($registry->applicable($resource::class, VersionsRowChecks::class) as $name => $extension) {
            $extensions[$name] = [$registry->classOf($name), $extension];
        }

        return $extensions;
    }

    private static function hooked(Resource $resource): bool
    {
        return self::$hooked[$resource::class] ??= Overrides::method($resource, 'versionAuthorization', Resource::class);
    }
}
