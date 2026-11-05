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
        $pivot = $this->pivot($node);
        $ids = [];

        foreach ($related as $model) {
            $ids[self::key($model)] = $pivot;
        }

        foreach ($related as $model) {
            $this->authorize($resource, $parent, $model, $node->operation);
        }

        $changes = match ($node->operation) {
            MutationOperation::Create, MutationOperation::Update => $this->pivots($parent)->syncWithoutDetaching($ids),
            MutationOperation::Attach => $this->pivots($parent)->attach($ids),
            MutationOperation::Detach => $this->pivots($parent)->detach($related),
            MutationOperation::Toggle => $this->pivots($parent)->toggle($ids),
            MutationOperation::Sync => $this->pivots($parent)->sync($ids, ! ($node->withoutDetaching ?? false)),
        };

        $resource
            ->context()
            ->authorizer()
            ->forget($parent);

        if (in_array($node->operation, [MutationOperation::Toggle, MutationOperation::Sync], true) && is_array($changes)) {
            (new PivotChanges($resource, $parent))->authorize($changes);
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
