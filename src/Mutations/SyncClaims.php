<?php

namespace Lomkit\Rest\Mutations;

use Illuminate\Database\Eloquent\Model;
use LogicException;
use Lomkit\Rest\Exceptions\ConflictReason;
use Lomkit\Rest\Exceptions\SyncConflict;
use Lomkit\Rest\Query\Clauses\PerimeterClause;

final class SyncClaims
{
    private const int CHUNK = 1000;

    /** @var list<SyncClaim> */
    private array $claims = [];

    public function claim(SyncClaim $claim): void
    {
        if ($claim->parent()->exists) {
            $this->claims[] = $claim;
        }
    }

    public function verify(): void
    {
        $this->lockParents();
        $conflicts = [];

        foreach ($this->claims as $claim) {
            $key = $this->unlisted($claim);

            if ($key !== null) {
                $conflicts[] = ['reason' => ConflictReason::Unlisted->value, 'path' => $claim->path, 'key' => $key];
            }
        }

        if ($conflicts !== []) {
            throw new SyncConflict($conflicts);
        }
    }

    private function lockParents(): void
    {
        $groups = [];

        foreach ($this->claims as $claim) {
            $parent = $claim->parent();
            $table = $parent->getConnection()->getName().'.'.$parent->getTable();
            $key = self::key($parent);
            $groups[$table] ??= [$parent, []];
            $groups[$table][1][$key] = $key;
        }

        foreach ($groups as [$model, $keys]) {
            foreach (array_chunk(array_values($keys), self::CHUNK) as $chunk) {
                $model
                    ->newQueryWithoutScopes()
                    ->whereKey($chunk)
                    ->orderBy($model->getQualifiedKeyName())
                    ->lockForUpdate()
                    ->toBase()
                    ->get([$model->getQualifiedKeyName()]);
            }
        }
    }

    private function unlisted(SyncClaim $claim): int|string|null
    {
        $children = $claim->children;
        $key = $children->getRelated()->getQualifiedKeyName();
        (new PerimeterClause($claim->relation->resource()))->apply($children);

        if ($claim->accounted !== []) {
            $children->whereNotIn($key, $claim->accounted);
        }

        $child = $children
            ->orderBy($key)
            ->lockForUpdate()
            ->first([$key]);

        return $child === null ? null : self::key($child);
    }

    private static function key(Model $row): int|string
    {
        $key = $row->getKey();

        if (! is_int($key) && ! is_string($key)) {
            throw new LogicException($row::class.' has a key that cannot name a row.');
        }

        return $key;
    }
}
