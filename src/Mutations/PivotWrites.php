<?php

namespace Lomkit\Rest\Mutations;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use LogicException;
use Lomkit\Rest\Contracts\PivotPersister;
use Lomkit\Rest\Contracts\PivotRelation;
use Lomkit\Rest\Fields\MutationOperation;
use Lomkit\Rest\Relations\BelongsToMany as PivotRelationDefinition;

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

        $plan = $mutator->checks->pivots->written($node);

        foreach (self::links($node, $related, $plan) as [$model, $detach]) {
            $mutator->checks->linkNow($this->relation, $node, $parent, $model, $detach);
        }

        $persister = $this->persister();
        $pivot = $this->pivot($node);
        $name = $this->relation->relation;

        if ($plan->detached !== []) {
            $persister->detach(new PivotWrite($parent, $name, $plan->detached, [], $node));
        }

        if ($plan->attached !== []) {
            $persister->attach(new PivotWrite($parent, $name, $plan->attached, $pivot, $node));
        }

        if ($plan->updated !== [] && $pivot !== []) {
            $persister->updatePivot(new PivotWrite($parent, $name, $plan->updated, $pivot, $node));
        }

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
            $plan = $checks->pivots->apply($this->relation, $parent, $node, $related);

            foreach (self::links($node, $related, $plan) as [$model, $detach]) {
                $checks->link($this->relation, $node, $parent, $model, $detach);
            }
        }
    }

    #[\Override]
    public function rehearseAfter(LinkedRow $parent, LinkReplay $replay): void
    {
        foreach ($this->nodes() as $node) {
            $rows = $replay->walk($this->relation->resource(), $node);
            $replay->pivots->add($parent, $this->pivots($parent->model), $node, $rows);
        }
    }

    /**
     * @param  list<Model>  $related
     * @return list<array{Model, bool}>
     */
    private static function links(MutationNode $node, array $related, PivotPlan $plan): array
    {
        $attached = $node->operation === MutationOperation::Update ? $related : [...$plan->attached, ...$plan->updated];

        return [
            ...array_map(fn (Model $model): array => [$model, false], $attached),
            ...array_map(fn (Model $model): array => [$model, true], $plan->detached),
        ];
    }

    private function persister(): PivotPersister
    {
        $persister = $this->relation instanceof PivotRelationDefinition ? $this->relation->getPivotPersister() : new EloquentPivots;

        return $persister instanceof EloquentPivots ? $persister : new VerifiedPersister($persister, $this->relation);
    }

    /** @return array<string, mixed> */
    private function pivot(MutationNode $node): array
    {
        $write = $node->operation->pivotWriteOperation();

        if ($write === null || $node->pivot === null || ! $this->relation instanceof PivotRelation) {
            return [];
        }

        $writable = $this->relation
            ->getPivotFields()
            ->writableOn($write);
        $names = array_flip($writable->names());
        $values = [];

        foreach ($node->pivot as $name => $value) {
            if (is_string($name) && isset($names[$name])) {
                $values[$name] = $value;
            }
        }

        return $writable->toStorage($values);
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
