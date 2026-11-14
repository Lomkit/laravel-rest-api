<?php

namespace Lomkit\Rest\Authorization;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

final class DecisionPreparation
{
    /** @var array<string, true> */
    private array $prepared = [];

    /** @param array<string, Check> $checks */
    public function prepare(array $checks): void
    {
        $groups = [];

        foreach (array_diff_key($checks, $this->prepared) as $key => $check) {
            $this->prepared[$key] = true;

            if (self::preparable($check)) {
                $groups[$check->resource::class.'|'.$check->ability][] = $check;
            }
        }

        foreach ($groups as $group) {
            $group[0]->resource->prepareAuthorization($group[0]->ability, self::rows($group));
        }
    }

    public function reset(): void
    {
        $this->prepared = [];
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
