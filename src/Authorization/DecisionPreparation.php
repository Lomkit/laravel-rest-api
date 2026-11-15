<?php

namespace Lomkit\Rest\Authorization;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\ExtensionRegistry;
use Lomkit\Rest\Extensions\Kinds\PreparesRowChecks;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Support\Overrides;

/** @internal */
final class DecisionPreparation
{
    /** @var array<class-string<Resource>, bool> */
    private static array $hooked = [];

    /** @var array<class-string<Resource>, array<string, Extension&PreparesRowChecks>> */
    private array $extensions = [];

    /** @var array<string, true> */
    private array $prepared = [];

    /** @param (Closure(): ExtensionRegistry)|null $registry */
    public function __construct(
        private readonly ?Authenticatable $user = null,
        private readonly ?Closure $registry = null,
    ) {}

    /**
     * @param  array<string, Check>  $checks
     * @param  Closure(string): bool  $decided
     */
    public function prepare(array $checks, Closure $decided): void
    {
        $groups = [];

        foreach ($checks as $key => $check) {
            if (! $this->prepares($check->resource) || isset($this->prepared[$key]) || $decided($key)) {
                continue;
            }

            $this->prepared[$key] = true;

            if (self::preparable($check)) {
                $groups[$check->resource::class.'|'.$check->ability][] = $check;
            }
        }

        foreach ($groups as $group) {
            $this->hand(new PendingChecks($group[0]->resource, $group[0]->ability, self::rows($group), $this->user));
        }
    }

    public function reset(): void
    {
        $this->prepared = [];
    }

    private function prepares(Resource $resource): bool
    {
        return self::hooked($resource) || $this->extensionsOf($resource) !== [];
    }

    private function hand(PendingChecks $checks): void
    {
        if (self::hooked($checks->resource)) {
            $checks->resource->prepareAuthorization($checks);
        }

        foreach ($this->extensionsOf($checks->resource) as $name => $extension) {
            $rows = PreparedRows::of($checks->rows);
            $extension->prepareRowChecks($checks);
            $rows->ensureUnchanged($name, $checks);
        }
    }

    /** @return array<string, Extension&PreparesRowChecks> */
    private function extensionsOf(Resource $resource): array
    {
        if ($this->registry === null) {
            return [];
        }

        if (! isset($this->extensions[$resource::class])) {
            $extensions = ($this->registry)()->applicable($resource::class, PreparesRowChecks::class);
            ksort($extensions);
            $this->extensions[$resource::class] = $extensions;
        }

        return $this->extensions[$resource::class];
    }

    private static function hooked(Resource $resource): bool
    {
        return self::$hooked[$resource::class] ??= Overrides::method($resource, 'prepareAuthorization', Resource::class);
    }

    private static function preparable(Check $check): bool
    {
        return $check->subject instanceof Model
            && $check->related === []
            && $check->subject->exists
            && ! $check->subject->isDirty();
    }

    /**
     * @param  non-empty-list<Check>  $group
     * @return Collection<int, Model>
     */
    private static function rows(array $group): Collection
    {
        $rows = [];

        foreach ($group as $check) {
            if ($check->subject instanceof Model) {
                $rows[spl_object_id($check->subject)] = $check->subject;
            }
        }

        return new Collection(array_values($rows));
    }
}
