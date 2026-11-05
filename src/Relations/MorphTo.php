<?php

namespace Lomkit\Rest\Relations;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo as EloquentMorphTo;
use LogicException;
use Lomkit\Rest\Contracts\EloquentQueryBuilder;
use Lomkit\Rest\Contracts\OwnerRelation;
use Lomkit\Rest\Contracts\PolymorphicRelation;
use Lomkit\Rest\Exceptions\InvalidResourceException;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Validation\Mutate\EntryRule;
use Lomkit\Rest\Validation\Mutate\MorphToEntryRule;

class MorphTo extends MorphRelation implements OwnerRelation, PolymorphicRelation
{
    private ?MorphTargets $targets = null;

    #[\Override]
    public function fromResource(Resource $fromResource): static
    {
        parent::fromResource($fromResource);

        $this->targets = MorphTargets::of([$this->type], $fromResource::class, $this->relation);

        return $this;
    }

    #[\Override]
    public function targets(): MorphTargets
    {
        return $this->targets ?? throw new LogicException("The MorphTo relation [{$this->relation}] is not bound to a resource.");
    }

    #[\Override]
    public function resourceOf(MorphTarget $target): Resource
    {
        return $this->fromResource->context()->resource($target->resource);
    }

    #[\Override]
    public function resourceFor(Model $model): ?Resource
    {
        $target = $this->targets()->forModel($model);

        return $target === null ? null : $this->resourceOf($target);
    }

    /** @param Builder<Model> $query */
    #[\Override]
    public function applySearchQuery(Builder $query): void
    {
        $resource = $this->resourceFor($query->getModel()) ?? throw new LogicException($query->getModel()::class." is not a target of the MorphTo relation [{$this->relation}].");

        self::perimeter($resource, $query);
    }

    #[\Override]
    public function entryRule(): EntryRule
    {
        return new MorphToEntryRule($this);
    }

    #[\Override]
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

    #[\Override]
    protected function constraintResource(): ?Resource
    {
        return $this->targets()->isPolymorphic() ? null : $this->resource();
    }
}
