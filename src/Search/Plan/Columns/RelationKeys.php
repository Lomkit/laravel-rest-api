<?php

namespace Lomkit\Rest\Search\Plan\Columns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;
use Illuminate\Database\Eloquent\Relations\HasOneOrManyThrough;
use Illuminate\Database\Eloquent\Relations\MorphOneOrMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use InvalidArgumentException;

abstract readonly class RelationKeys
{
    /** @param Relation<*, *, *> $relation */
    public static function of(Relation $relation): self
    {
        return match (true) {
            $relation instanceof MorphTo => new MorphToKeys($relation),
            $relation instanceof BelongsTo => new BelongsToKeys($relation),
            $relation instanceof MorphOneOrMany => new MorphOneOrManyKeys($relation),
            $relation instanceof HasOneOrMany => new HasOneOrManyKeys($relation),
            $relation instanceof BelongsToMany => new BelongsToManyKeys($relation),
            $relation instanceof HasOneOrManyThrough => new HasOneOrManyThroughKeys($relation),
            default => new UnknownRelationKeys,
        };
    }

    public static function between(Model $parent, string $relation): self
    {
        $instance = Relation::noConstraints(static fn (): mixed => $parent->{$relation}());

        if (! $instance instanceof Relation) {
            throw new InvalidArgumentException($parent::class."::{$relation}() is not a relation.");
        }

        return self::of($instance);
    }

    /** @return list<string> */
    abstract public function parent(): array;

    /** @return list<string> */
    abstract public function related(): array;
}
