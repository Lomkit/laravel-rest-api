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
            $owner = $this->owner($parent);
            $this->authorize($resource, $parent, $related, $node->operation);

            if ($node->operation === MutationOperation::Detach) {
                $owner->dissociate();

                continue;
            }

            $owner->associate($related);
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

            $value = $related->owner($owner->getOwnerKeyName(), $owner instanceof EloquentMorphTo ? $related->model->getMorphClass() : null);
            $detach = $node->operation === MutationOperation::Detach;

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
    protected function checksLinksAsAttach(): bool
    {
        return $this->relation instanceof PolymorphicRelation;
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
}
