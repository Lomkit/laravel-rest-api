<?php

namespace Lomkit\Rest\Console\Generation;

use Illuminate\Database\Eloquent\Relations\BelongsTo as EloquentBelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany as EloquentBelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany as EloquentHasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough as EloquentHasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne as EloquentHasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough as EloquentHasOneThrough;
use Illuminate\Database\Eloquent\Relations\MorphMany as EloquentMorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne as EloquentMorphOne;
use Illuminate\Database\Eloquent\Relations\MorphTo as EloquentMorphTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany as EloquentMorphToMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Lomkit\Rest\Relations\BelongsTo;
use Lomkit\Rest\Relations\BelongsToMany;
use Lomkit\Rest\Relations\HasMany;
use Lomkit\Rest\Relations\HasManyThrough;
use Lomkit\Rest\Relations\HasOne;
use Lomkit\Rest\Relations\HasOneThrough;
use Lomkit\Rest\Relations\MorphedByMany;
use Lomkit\Rest\Relations\MorphMany;
use Lomkit\Rest\Relations\MorphOne;
use Lomkit\Rest\Relations\MorphToMany;

enum RelationType: string
{
    case BelongsTo = BelongsTo::class;
    case BelongsToMany = BelongsToMany::class;
    case HasMany = HasMany::class;
    case HasManyThrough = HasManyThrough::class;
    case HasOne = HasOne::class;
    case HasOneThrough = HasOneThrough::class;
    case MorphedByMany = MorphedByMany::class;
    case MorphMany = MorphMany::class;
    case MorphOne = MorphOne::class;
    case MorphToMany = MorphToMany::class;

    /** @param  Relation<*, *, *>  $relation */
    public static function of(Relation $relation): ?self
    {
        return match (true) {
            $relation instanceof EloquentMorphTo => null,
            $relation instanceof EloquentBelongsTo => self::BelongsTo,
            $relation instanceof EloquentMorphToMany => $relation->getInverse() ? self::MorphedByMany : self::MorphToMany,
            $relation instanceof EloquentBelongsToMany => self::BelongsToMany,
            $relation instanceof EloquentMorphOne => self::MorphOne,
            $relation instanceof EloquentMorphMany => self::MorphMany,
            $relation instanceof EloquentHasOne => self::HasOne,
            $relation instanceof EloquentHasMany => self::HasMany,
            $relation instanceof EloquentHasOneThrough => self::HasOneThrough,
            $relation instanceof EloquentHasManyThrough => self::HasManyThrough,
            default => null,
        };
    }
}
