<?php

namespace Lomkit\Rest\Query;

use Closure;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use LogicException;

final readonly class MorphHas
{
    /** @param list<class-string<Model>> $models */
    public function __construct(
        private string $relation,
        private array $models,
        private bool $textKeys,
    ) {}

    /** @param Closure(EloquentBuilder<Model>): void $constraint */
    public function apply(Builder $query, string $boolean, Closure $constraint): void
    {
        $query->where(function (EloquentBuilder $query) use ($constraint): void {
            $morphTo = $this->morphTo($query->getModel());

            foreach ($this->models as $model) {
                $query->orWhere(function (EloquentBuilder $query) use ($morphTo, $model, $constraint): void {
                    $query
                        ->where($query->qualifyColumn($morphTo->getMorphType()), '=', (new $model)->getMorphClass())
                        ->whereHas($this->belongsTo($morphTo, $query->getModel(), $model), $constraint);
                });
            }
        }, null, null, $boolean);
    }

    /** @return MorphTo<Model, Model> */
    private function morphTo(Model $parent): MorphTo
    {
        $relation = Relation::noConstraints(fn (): mixed => $parent->{$this->relation}());

        if (! $relation instanceof MorphTo) {
            throw new LogicException($parent::class.'::'.$this->relation.'() is not a MorphTo relation.');
        }

        return $relation;
    }

    /**
     * @param  MorphTo<Model, Model>  $morphTo
     * @param  class-string<Model>  $model
     * @return BelongsTo<Model, Model>
     */
    private function belongsTo(MorphTo $morphTo, Model $parent, string $model): BelongsTo
    {
        $belongsTo = Relation::noConstraints(function () use ($morphTo, $parent, $model): BelongsTo {
            $relation = $parent->belongsTo($model, $morphTo->getForeignKeyName(), $morphTo->getOwnerKeyName(), $morphTo->getRelationName());

            return $this->textKeys ? new TextKeyBelongsTo($relation->getQuery(), $parent, $relation->getForeignKeyName(), $relation->getOwnerKeyName(), $relation->getRelationName()) : $relation;
        });

        $morphTo->replayMacros($belongsTo->getQuery());
        $belongsTo->getQuery()->mergeConstraintsFrom($morphTo->getQuery());

        return $belongsTo;
    }
}
