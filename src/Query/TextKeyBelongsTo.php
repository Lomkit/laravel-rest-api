<?php

namespace Lomkit\Rest\Query;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Lomkit\Rest\Query\Expressions\TextColumn;

/**
 * @template TRelatedModel of Model
 * @template TDeclaringModel of Model
 *
 * @extends BelongsTo<TRelatedModel, TDeclaringModel>
 */
final class TextKeyBelongsTo extends BelongsTo
{
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

        return $query->select($columns)->where($this->getQualifiedForeignKeyName(), '=', new TextColumn($query->qualifyColumn($this->ownerKey)));
    }
}
