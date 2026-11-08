<?php

namespace Lomkit\Rest\Query;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOneOrMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\Relations\Relation as EloquentRelation;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Relations\MorphTo;
use Lomkit\Rest\Relations\Relation;

final readonly class TextMorphKeys
{
    public static function of(Relation $relation): bool
    {
        $model = $relation->parentResource()::newModel();

        if ($model->getConnection()->getDriverName() !== 'pgsql') {
            return false;
        }

        $eloquent = self::eloquent($model, $relation->relation);

        if (! TextKeyRelations::supports($eloquent)) {
            return false;
        }

        return match (true) {
            $eloquent instanceof MorphOneOrMany => self::integerKey($eloquent->getParent(), $eloquent->getLocalKeyName()) && self::morphToHoldsText($relation->resource(), $eloquent),
            $eloquent instanceof MorphToMany && $eloquent->getInverse() => self::integerKey($eloquent->getRelated(), $eloquent->getRelatedKeyName()) && self::morphedHoldText($relation->parentResource(), $eloquent),
            $eloquent instanceof MorphToMany => self::integerKey($eloquent->getParent(), $eloquent->getParentKeyName()) && self::morphedHoldText($relation->resource(), $eloquent),
            default => false,
        };
    }

    /** @param MorphOneOrMany<Model, Model, mixed> $eloquent */
    private static function morphToHoldsText(Resource $morph, MorphOneOrMany $eloquent): bool
    {
        foreach ($morph->definition()->relations() as $relation) {
            if (! $relation instanceof MorphTo) {
                continue;
            }

            $columns = $relation->columns();

            if ($columns->typeColumn === $eloquent->getMorphType() && $columns->keyColumn === $eloquent->getForeignKeyName()) {
                return self::anyTextKey($relation->targets()->modelClasses());
            }
        }

        return false;
    }

    /** @param MorphToMany<Model, Model> $eloquent */
    private static function morphedHoldText(Resource $owner, MorphToMany $eloquent): bool
    {
        $models = [];

        foreach ($owner->definition()->relations() as $relation) {
            $sibling = self::eloquent($owner::newModel(), $relation->relation);

            if ($sibling instanceof MorphToMany && $sibling->getInverse() && $sibling->getTable() === $eloquent->getTable() && $sibling->getMorphType() === $eloquent->getMorphType()) {
                $models[] = $sibling->getRelated()::class;
            }
        }

        return self::anyTextKey($models);
    }

    /** @param list<class-string<Model>> $models */
    private static function anyTextKey(array $models): bool
    {
        return array_any($models, static fn (string $model): bool => ! self::integerKeyType(new $model));
    }

    private static function integerKey(Model $model, string $key): bool
    {
        return $model->getKeyName() === $key && self::integerKeyType($model);
    }

    private static function integerKeyType(Model $model): bool
    {
        return in_array($model->getKeyType(), ['int', 'integer'], true);
    }

    private static function eloquent(Model $model, string $name): mixed
    {
        return EloquentRelation::noConstraintsForRelation(static fn (): mixed => $model->{$name}());
    }
}
