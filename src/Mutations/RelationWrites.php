<?php

namespace Lomkit\Rest\Mutations;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;
use Lomkit\Rest\Fields\MutationOperation;
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

    public function before(Model $parent, Mutator $mutator, OperationLog $log): void {}

    /** @return array<string, mixed> */
    public function owned(Model $parent): array
    {
        return [];
    }

    public function after(Model $parent, Mutator $mutator, OperationLog $log): void {}

    public function rehearseBefore(LinkedRow $parent, LinkReplay $replay): void {}

    public function rehearseAfter(LinkedRow $parent, LinkReplay $replay): void {}

    protected function authorize(Resource $resource, Model $parent, Model $related, MutationOperation $operation): void
    {
        $ability = match ($operation) {
            MutationOperation::Create, MutationOperation::Update, MutationOperation::Attach => 'attach',
            MutationOperation::Detach => 'detach',
            MutationOperation::Sync, MutationOperation::Toggle => $this->checksLinksAsAttach() ? 'attach' : null,
        };

        if ($ability === 'attach') {
            $resource->authorizeToAttach($parent, $related);
        }

        if ($ability === 'detach') {
            $resource->authorizeToDetach($parent, $related);
        }
    }

    protected function checksLinksAsAttach(): bool
    {
        return false;
    }

    /** @return list<MutationNode> */
    protected function nodes(): array
    {
        return $this->entry instanceof MutationNode ? [$this->entry] : $this->entry;
    }
}
