<?php

namespace Lomkit\Rest\Authorization;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Support\Overrides;

final class DecisionPreparation
{
    /** @var array<class-string<Resource>, bool> */
    private static array $hooked = [];

    /** @var array<string, true> */
    private array $prepared = [];

    public function __construct(private readonly ?Authenticatable $user = null) {}

    /**
     * @param  array<string, Check>  $checks
     * @param  Closure(string): bool  $decided
     */
    public function prepare(array $checks, Closure $decided): void
    {
        $groups = [];

        foreach ($checks as $key => $check) {
            if (! self::hooked($check->resource) || isset($this->prepared[$key]) || $decided($key)) {
                continue;
            }

            $this->prepared[$key] = true;

            if (self::preparable($check)) {
                $groups[$check->resource::class.'|'.$check->ability][] = $check;
            }
        }

        foreach ($groups as $group) {
            $group[0]->resource->prepareAuthorization(new PendingChecks($group[0]->ability, self::rows($group), $this->user));
        }
    }

    public function reset(): void
    {
        $this->prepared = [];
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
