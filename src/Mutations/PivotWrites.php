<?php

namespace Lomkit\Rest\Mutations;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use LogicException;
use Lomkit\Rest\Contracts\PivotRelation;
use Lomkit\Rest\Fields\MutationOperation;

final readonly class PivotWrites extends RelationWrites
{
    #[\Override]
    public function after(Model $parent, Mutator $mutator, OperationLog $log): void
    {
        foreach ($this->nodes() as $node) {
            $this->write($parent, $node, $mutator, $log);
        }
    }

    private function write(Model $parent, MutationNode $node, Mutator $mutator, OperationLog $log): void
    {
        $resource = $this->relation->resource();
        $related = $node->operation === MutationOperation::Create
            ? [$mutator->one($resource, $node, $log)]
            : $mutator->each($resource, $node, $log);

        if ($node->operation === MutationOperation::Create) {
            $mutator->checks->pivots->created($node, $related[0]);
        }

        $pivot = $this->pivot($node);
        $ids = [];

        foreach ($related as $model) {
            $ids[self::key($model)] = $pivot;
        }

        [$attached, $detached] = match ($node->operation) {
            MutationOperation::Sync, MutationOperation::Toggle => $mutator->checks->pivots->written($node),
            MutationOperation::Detach => [[], $related],
            default => [$related, []],
        };

        foreach ($attached as $model) {
            $mutator->checks->linkNow($this->relation, $node, $parent, $model, false);
        }

        foreach ($detached as $model) {
            $mutator->checks->linkNow($this->relation, $node, $parent, $model, true);
        }

        $pivots = $this->pivots($parent);

        match ($node->operation) {
            MutationOperation::Create, MutationOperation::Update => $pivots->syncWithoutDetaching($ids),
            MutationOperation::Attach => $pivots->attach($ids),
            MutationOperation::Detach => $pivots->detach($related),
            MutationOperation::Toggle => $pivots->toggle($ids),
            MutationOperation::Sync => $pivots->sync($ids, ! ($node->withoutDetaching ?? false)),
        };

        $resource
            ->context()
            ->authorizer()
            ->forget($parent);
    }

    #[\Override]
    public function check(Model $parent, WriteChecks $checks): void
    {
        foreach ($this->nodes() as $node) {
            $related = $checks->walk($this->relation->resource(), $node);
            [$attached, $detached] = $checks->pivots->apply($this->relation, $parent, $node, $related);

            foreach ($attached as $model) {
                $checks->link($this->relation, $node, $parent, $model, false);
            }

            foreach ($detached as $model) {
                $checks->link($this->relation, $node, $parent, $model, true);
            }
        }
    }

    #[\Override]
    public function rehearseAfter(LinkedRow $parent, LinkReplay $replay): void
    {
        foreach ($this->nodes() as $node) {
            $replay->walk($this->relation->resource(), $node);
        }
    }

    private static function key(Model $model): int|string
    {
        $key = $model->getKey();

        return is_int($key) || is_string($key) ? $key : throw new LogicException($model::class.' has a key that cannot name a pivot row.');
    }

    /** @return array<array-key, mixed> */
    private function pivot(MutationNode $node): array
    {
        $write = $node->operation->pivotWriteOperation();

        if ($write === null || $node->pivot === null || ! $this->relation instanceof PivotRelation) {
            return [];
        }

        $writable = $this->relation
            ->getPivotFields()
            ->writableOn($write);

        return $writable->toStorage(array_intersect_key($node->pivot, array_flip($writable->names())));
    }

    /** @return BelongsToMany<Model, Model> */
    private function pivots(Model $parent): BelongsToMany
    {
        $pivots = $parent->{$this->relation->relation}();

        if (! $pivots instanceof BelongsToMany) {
            throw new LogicException("The relation [{$this->relation->relation}] is not a BelongsToMany, MorphToMany or MorphedByMany relation.");
        }

        return $pivots;
    }
}
