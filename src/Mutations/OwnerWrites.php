<?php

namespace Lomkit\Rest\Mutations;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo as EloquentBelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo as EloquentMorphTo;
use LogicException;
use Lomkit\Rest\Contracts\PolymorphicRelation;
use Lomkit\Rest\Fields\MutationOperation;
use Lomkit\Rest\Http\Resource;

final readonly class OwnerWrites extends RelationWrites
{
    #[\Override]
    public function before(Model $parent, Mutator $mutator, OperationLog $log): void
    {
        foreach ($this->nodes() as $node) {
            $resource = $this->resource($node);
            $related = $mutator->one($resource, $node, $log);
            $mutator->checks->linkNow($this->relation, $node, $parent, $related, $node->operation === MutationOperation::Detach);

            if ($node->operation === MutationOperation::Detach) {
                $this->owner($parent)->dissociate();

                continue;
            }

            $this->ownerFor($parent, $related)->associate($related);
        }
    }

    #[\Override]
    public function rehearseBefore(LinkedRow $parent, LinkReplay $replay): void
    {
        $owner = $this->owner($parent->model);
        $column = LinkColumn::ofOwner($owner);

        foreach ($this->nodes() as $node) {
            $related = $replay->walk($this->resource($node), $node)[0] ?? null;

            if ($related === null) {
                continue;
            }

            $value = $related->owner($this->ownerKey($parent->model, $related->model), $owner instanceof EloquentMorphTo ? $related->model->getMorphClass() : null);
            $detach = $node->operation === MutationOperation::Detach;

            if ($detach) {
                $replay->clear($node->path.'.key', $column);
            }

            $replay->add(new LinkStep($node->path.'.key', $column, $parent->identity, $parent->link($column), $value, $detach, $detach ? LinkCheck::Current : LinkCheck::None));
        }
    }

    /** @return array<string, mixed> */
    #[\Override]
    public function owned(Model $parent): array
    {
        $owner = $this->owner($parent);
        $keys = [$owner->getForeignKeyName() => $parent->getAttribute($owner->getForeignKeyName())];

        if (! $owner instanceof EloquentMorphTo) {
            return $keys;
        }

        return [...$keys, $owner->getMorphType() => $parent->getAttribute($owner->getMorphType())];
    }

    #[\Override]
    public function plannedKeys(Model $parent, WriteChecks $checks): PlannedKeys
    {
        $owner = $this->owner($parent);
        $foreignKey = $owner->getForeignKeyName();
        $planned = new PlannedKeys;

        foreach ($this->nodes() as $node) {
            $related = match ($node->operation) {
                MutationOperation::Detach => null,
                MutationOperation::Create => $checks->draft($this->resource($node), $node),
                default => $checks->row($this->resource($node), $node->key),
            };

            if ($related === null && $node->operation !== MutationOperation::Detach) {
                continue;
            }

            $keys = [$foreignKey => $related === null ? null : $related->getAttribute($this->ownerKey($parent, $related))];

            if ($owner instanceof EloquentMorphTo) {
                $keys[$owner->getMorphType()] = $related?->getMorphClass();
            }

            $planned = $planned->with(new PlannedKeys($keys, $related === null ? [] : [$foreignKey => $related]));
        }

        return $planned;
    }

    #[\Override]
    public function check(Model $parent, WriteChecks $checks): void
    {
        foreach ($this->nodes() as $node) {
            foreach ($checks->walk($this->resource($node), $node) as $related) {
                $checks->link($this->relation, $node, $parent, $related, $node->operation === MutationOperation::Detach);
            }
        }
    }

    private function resource(MutationNode $node): Resource
    {
        if (! $this->relation instanceof PolymorphicRelation) {
            return $this->relation->resource();
        }

        $target = $this->relation->targets()->forType($node->type) ?? throw new LogicException("The entry of the MorphTo relation [{$this->relation->relation}] names no target.");

        return $this->relation->resourceOf($target);
    }

    /** @return EloquentBelongsTo<Model, Model> */
    private function owner(Model $parent): EloquentBelongsTo
    {
        $owner = $parent->{$this->relation->relation}();

        if ($this->relation instanceof PolymorphicRelation && ! $owner instanceof EloquentMorphTo) {
            throw new LogicException("The relation [{$this->relation->relation}] is declared with MorphTo, but its model method does not return a MorphTo relation.");
        }

        if (! $owner instanceof EloquentBelongsTo) {
            throw new LogicException("The relation [{$this->relation->relation}] is not a BelongsTo or MorphTo relation.");
        }

        return $owner;
    }

    /** @return EloquentBelongsTo<Model, Model> */
    private function ownerFor(Model $parent, Model $related): EloquentBelongsTo
    {
        $owner = $this->owner($parent);

        if (! $owner instanceof EloquentMorphTo) {
            return $owner;
        }

        $parent->setAttribute($owner->getMorphType(), $related->getMorphClass());

        return $this->owner($parent);
    }

    private function ownerKey(Model $parent, Model $related): string
    {
        return $this->ownerFor($parent->newInstance(), $related)->getOwnerKeyName();
    }
}
