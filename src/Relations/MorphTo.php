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
use Lomkit\Rest\Fields\MutationOperation;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Validation\Mutate\EntryRule;
use Lomkit\Rest\Validation\Mutate\MorphToEntryRule;

class MorphTo extends MorphRelation implements OwnerRelation, PolymorphicRelation
{
    /** @var array<array-key, mixed> */
    private readonly array $resources;

    private ?MorphTargets $targets = null;

    /** @param class-string<Resource>|list<class-string<Resource>> $types */
    public function __construct(string $relation, string|array $types)
    {
        $resources = is_array($types) ? $types : [$types];
        $first = reset($resources);

        parent::__construct($relation, is_string($first) ? $first : '');

        $this->resources = $resources;
    }

    #[\Override]
    public function fromResource(Resource $fromResource): static
    {
        parent::fromResource($fromResource);

        $this->targets = MorphTargets::of($this->resources, $fromResource::class, $this->relation);

        return $this;
    }

    #[\Override]
    public function targets(): MorphTargets
    {
        return $this->targets ?? throw new LogicException("The MorphTo relation [{$this->relation}] is not bound to a resource.");
    }

    #[\Override]
    public function resource(): Resource
    {
        $target = $this->targets()->single() ?? throw new LogicException("The MorphTo relation [{$this->relation}] has several targets: resolve one with targets().");

        return $this->resourceOf($target);
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
        $details = [
            ...parent::toDetails(),
            'types' => (object) $this->targets()->byType(),
            'morph' => $this->columns(),
        ];

        if ($this->targets()->isPolymorphic()) {
            unset($details['resource']);
        }

        return $details;
    }

    /** @param array<string, array<string, mixed>> $mutationRelations */
    #[\Override]
    public function beforeMutating(Model $model, Relation $relation, array $mutationRelations): void
    {
        $entry = $mutationRelations[$this->relation];
        $target = $this->targets()->forType($entry['type'] ?? null) ?? throw new LogicException("The entry of the MorphTo relation [{$this->relation}] names no target.");
        $resource = $this->resourceOf($target);
        $operation = MutationOperation::from(is_string($entry['operation'] ?? null) ? $entry['operation'] : '');
        $related = app()->make(EloquentQueryBuilder::class, ['resource' => $resource])
            ->applyMutation($entry);
        $morphTo = $model->{$this->relation}();

        if (! $morphTo instanceof EloquentMorphTo) {
            throw InvalidResourceException::notAMorphTo($this->fromResource::class, $this->relation);
        }

        if ($operation === MutationOperation::Detach) {
            $resource->authorizeToDetach($model, $related);
            $morphTo->dissociate();

            return;
        }

        $resource->authorizeToAttach($model, $related);
        $morphTo->associate($related);
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
