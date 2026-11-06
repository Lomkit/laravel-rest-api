<?php

namespace Lomkit\Rest\Mutations;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Relations\Relation;

abstract readonly class RelationWrites
{
    /** @param MutationNode|list<MutationNode> $entry */
    final public function __construct(
        public Relation $relation,
        protected MutationNode|array $entry,
    ) {}

    /** @param MutationNode|list<MutationNode> $entry */
    public static function of(Model $parent, Relation $relation, MutationNode|array $entry): self
    {
        $eloquent = $parent->{$relation->relation}();

        return match (true) {
            $eloquent instanceof BelongsTo => new OwnerWrites($relation, $entry),
            $eloquent instanceof HasOneOrMany => new ChildWrites($relation, $entry),
            $eloquent instanceof BelongsToMany => new PivotWrites($relation, $entry),
            default => new NoWrites($relation, $entry),
        };
    }

    /** @return list<self> */
    public static function ofNode(Resource $resource, Model $row, MutationNode $node): array
    {
        $entries = $node->relations ?? [];
        $writes = [];

        foreach ($resource->definition()->relations() as $relation) {
            if (isset($entries[$relation->relation])) {
                $writes[] = self::of($row, $relation, $entries[$relation->relation]);
            }
        }

        return $writes;
    }

    public function before(Model $parent, Mutator $mutator, OperationLog $log): void {}

    /** @return array<string, mixed> */
    public function owned(Model $parent): array
    {
        return [];
    }

    public function after(Model $parent, Mutator $mutator, OperationLog $log): void {}

    public function rehearseBefore(LinkedRow $parent, LinkReplay $replay): void {}

    public function rehearseAfter(LinkedRow $parent, LinkReplay $replay): void {}

    /** @return array<string, mixed> */
    public function plannedKeys(Model $parent, WriteChecks $checks): array
    {
        return [];
    }

    public function check(Model $parent, WriteChecks $checks): void {}

    /** @return list<MutationNode> */
    protected function nodes(): array
    {
        return $this->entry instanceof MutationNode ? [$this->entry] : $this->entry;
    }
}
