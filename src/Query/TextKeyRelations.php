<?php

namespace Lomkit\Rest\Query;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use LogicException;

final readonly class TextKeyRelations
{
    private const array CLASSES = [MorphMany::class, MorphOne::class, MorphToMany::class];

    public static function supports(mixed $relation): bool
    {
        if (! is_object($relation) || ! in_array($relation::class, self::CLASSES, true)) {
            return false;
        }

        return ! $relation instanceof MorphOne || ! $relation->isOneOfMany();
    }

    /**
     * @param  Relation<*, *, *>  $relation
     * @return Relation<*, *, *>
     */
    public static function of(Relation $relation): Relation
    {
        return match ($relation::class) {
            MorphMany::class => TextKeyMorphMany::copy($relation),
            MorphOne::class => TextKeyMorphOne::copy($relation),
            MorphToMany::class => TextKeyMorphToMany::copy($relation),
            default => throw new LogicException($relation::class.' cannot compare its keys as text.'),
        };
    }
}
