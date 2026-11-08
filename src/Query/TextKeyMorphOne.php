<?php

namespace Lomkit\Rest\Query;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Lomkit\Rest\Query\Expressions\TextColumn;

/**
 * @template TRelatedModel of Model
 * @template TDeclaringModel of Model
 *
 * @extends MorphOne<TRelatedModel, TDeclaringModel>
 */
final class TextKeyMorphOne extends MorphOne
{
    use CopiesRelation;

    /** @param array<int, TDeclaringModel> $models */
    #[\Override]
    public function addEagerConstraints(array $models): void
    {
        $keys = array_map(strval(...), $this->getKeys($models, $this->localKey));

        $this->whereInEager('whereIn', $this->foreignKey, $keys, $this->query);
        $this->query->where($this->morphType, $this->morphClass);
    }

    /**
     * @param  Builder<TRelatedModel>  $query
     * @param  Builder<TDeclaringModel>  $parentQuery
     * @return Builder<TRelatedModel>
     */
    #[\Override]
    public function getRelationExistenceQuery(Builder $query, Builder $parentQuery, mixed $columns = ['*']): Builder
    {
        if ($query->getQuery()->from === $parentQuery->getQuery()->from) {
            return parent::getRelationExistenceQuery($query, $parentQuery, $columns);
        }

        return $query
            ->select($columns)
            ->whereColumn(new TextColumn($this->getQualifiedParentKeyName()), '=', $this->getExistenceCompareKey())
            ->where($query->qualifyColumn($this->getMorphType()), $this->morphClass);
    }
}
