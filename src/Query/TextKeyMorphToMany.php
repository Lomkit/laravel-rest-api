<?php

namespace Lomkit\Rest\Query;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\JoinClause;
use Lomkit\Rest\Query\Expressions\TextColumn;

/**
 * @template TRelatedModel of Model
 * @template TDeclaringModel of Model
 *
 * @extends MorphToMany<TRelatedModel, TDeclaringModel>
 */
final class TextKeyMorphToMany extends MorphToMany
{
    use CopiesRelation {
        copy as private copyRelation;
    }

    /** @param Relation<*, *, *> $relation */
    public static function copy(Relation $relation): static
    {
        $copy = self::copyRelation($relation);

        if ($copy->inverse) {
            $base = $copy->query->getQuery();
            $base->joins = array_values(array_filter($base->joins ?? [], static fn (mixed $join): bool => ! ($join instanceof JoinClause && $join->table === $copy->table)));
            $copy->performJoin();
        }

        return $copy;
    }

    /** @param array<int, TDeclaringModel> $models */
    #[\Override]
    public function addEagerConstraints(array $models): void
    {
        if ($this->inverse) {
            parent::addEagerConstraints($models);

            return;
        }

        $this->whereInEager('whereIn', $this->getQualifiedForeignPivotKeyName(), array_map(strval(...), $this->getKeys($models, $this->parentKey)));
        $this->query->where($this->qualifyPivotColumn($this->morphType), $this->morphClass);
    }

    /**
     * @param  Builder<TRelatedModel>  $query
     * @param  Builder<TDeclaringModel>  $parentQuery
     * @return Builder<TRelatedModel>
     */
    #[\Override]
    public function getRelationExistenceQuery(Builder $query, Builder $parentQuery, mixed $columns = ['*']): Builder
    {
        if ($parentQuery->getQuery()->from === $query->getQuery()->from) {
            return parent::getRelationExistenceQuery($query, $parentQuery, $columns);
        }

        $this->performJoin($query);
        $parentKey = $this->inverse ? $this->getQualifiedParentKeyName() : new TextColumn($this->getQualifiedParentKeyName());

        return $query
            ->select($columns)
            ->whereColumn($parentKey, '=', $this->getExistenceCompareKey())
            ->where($this->qualifyPivotColumn($this->morphType), $this->morphClass);
    }

    /** @param Builder<TRelatedModel>|null $query */
    #[\Override]
    protected function performJoin($query = null): static
    {
        if (! $this->inverse) {
            return parent::performJoin($query);
        }

        ($query ?? $this->query)->join($this->table, new TextColumn($this->getQualifiedRelatedKeyName()), '=', $this->getQualifiedRelatedPivotKeyName());

        return $this;
    }
}
