<?php

namespace Lomkit\Rest\Mutations;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOneOrMany;
use LogicException;
use Lomkit\Rest\Fields\MutationOperation;

final readonly class ChildWrites extends RelationWrites
{
    #[\Override]
    public function after(Model $parent, Mutator $mutator, OperationLog $log): void
    {
        $resource = $this->relation->resource();

        foreach ($this->nodes() as $node) {
            $detach = $node->operation === MutationOperation::Detach;
            $keys = $this->keys($parent, $detach);
            $children = $node->operation === MutationOperation::Create || ! $this->relation->hasMultipleEntries()
                ? [$mutator->one($resource, $node, $log, $keys)]
                : $mutator->each($resource, $node, $log, $keys);

            foreach ($children as $child) {
                $this->authorize($resource, $parent, $child, $node->operation);
            }
        }
    }

    #[\Override]
    protected function checksLinksAsAttach(): bool
    {
        return $this->relation->hasMultipleEntries();
    }

    /** @return array<string, mixed> */
    private function keys(Model $parent, bool $detach): array
    {
        $children = $parent->{$this->relation->relation}();

        if (! $children instanceof HasOneOrMany) {
            throw new LogicException("The relation [{$this->relation->relation}] is not a HasOne, HasMany, MorphOne or MorphMany relation.");
        }

        $keys = [$children->getForeignKeyName() => $detach ? null : $children->getParentKey()];

        if (! $children instanceof MorphOneOrMany) {
            return $keys;
        }

        return [...$keys, $children->getMorphType() => $detach && ! $children instanceof MorphMany ? null : $parent->getMorphClass()];
    }
}
