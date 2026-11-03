<?php

namespace Lomkit\Rest\Relations;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo as EloquentMorphTo;
use Lomkit\Rest\Contracts\EloquentQueryBuilder;
use Lomkit\Rest\Exceptions\InvalidResourceException;

class MorphTo extends MorphRelation
{
    public function targets(): MorphTargets
    {
        return MorphTargets::of($this->type);
    }

    public function columns(): MorphColumns
    {
        $relation = $this->modelRelation();

        if (! $relation instanceof EloquentMorphTo) {
            throw InvalidResourceException::notAMorphTo($this->fromResource::class, $this->relation);
        }

        return MorphColumns::of($relation);
    }

    /** @return list<string> */
    #[\Override]
    public function reservedKeys(): array
    {
        return [$this->fromResource->context()->config()->morphTypeKey];
    }

    /** @return array<string, mixed> */
    #[\Override]
    public function toDetails(): array
    {
        return [
            ...parent::toDetails(),
            'types' => (object) $this->targets()->byType(),
            'morph' => $this->columns(),
        ];
    }

    /**
     * Perform actions before mutating the MorphTo relation.
     *
     * @param  Model  $model  The Eloquent model.
     * @param  Relation  $relation  The relation being mutated.
     * @param  array  $mutationRelations  An array of mutation relations.
     */
    #[\Override]
    public function beforeMutating(Model $model, Relation $relation, array $mutationRelations)
    {
        $toPerformActionModel = app()->make(EloquentQueryBuilder::class, ['resource' => $relation->resource()])
            ->applyMutation($mutationRelations[$relation->relation]);

        switch ($mutationRelations[$relation->relation]['operation']) {
            case 'create':
            case 'update':
            case 'attach':
                $this->resource()->authorizeToAttach($model, $toPerformActionModel);
                break;
            case 'detach':
                $this->resource()->authorizeToDetach($model, $toPerformActionModel);
                break;
        }

        $model
            ->{$relation->relation}()
            ->{$mutationRelations[$relation->relation]['operation'] === 'detach' ? 'dissociate' : 'associate'}(
                $toPerformActionModel
            );
    }

    /** @return array<string, mixed> */
    #[\Override]
    public function ownedAttributes(Model $model): array
    {
        $relation = $model->{$this->relation}();

        if (! $relation instanceof EloquentMorphTo) {
            return [];
        }

        return [
            $relation->getForeignKeyName() => $model->getAttribute($relation->getForeignKeyName()),
            $relation->getMorphType() => $model->getAttribute($relation->getMorphType()),
        ];
    }
}
