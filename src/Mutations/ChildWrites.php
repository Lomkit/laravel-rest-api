<?php

namespace Lomkit\Rest\Mutations;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphOneOrMany;
use LogicException;
use Lomkit\Rest\Fields\MutationOperation;
use Lomkit\Rest\Relations\ChildRemoval;

final readonly class ChildWrites extends RelationWrites
{
    #[\Override]
    public function after(Model $parent, Mutator $mutator, OperationLog $log): void
    {
        $resource = $this->relation->resource();

        foreach ($this->nodes() as $node) {
            if ($node->operation === MutationOperation::Delete) {
                $mutator->delete($resource, $node, $log);

                continue;
            }

            $removal = $this->removal($node);

            if ($removal !== null) {
                $this->sync($parent, $node, $removal, $mutator, $log);

                continue;
            }

            $detach = $node->operation === MutationOperation::Detach;
            $keys = $this->keys($parent, $detach);
            $linking = function (Model $child) use ($mutator, $node, $parent, $detach): void {
                $mutator->checks->linkNow($this->relation, $node, $parent, $child, $detach);
            };

            if ($node->operation === MutationOperation::Create || ! $this->relation->hasMultipleEntries()) {
                $mutator->one($resource, $node, $log, $keys, $linking);

                continue;
            }

            $mutator->each($resource, $node, $log, $keys, $linking);
        }
    }

    #[\Override]
    public function rehearseAfter(LinkedRow $parent, LinkReplay $replay): void
    {
        $children = $this->children($parent->model);
        $column = LinkColumn::ofChildren($children);
        $type = $children instanceof MorphOneOrMany ? $children->getMorphClass() : null;
        $owner = $parent->owner($children->getLocalKeyName(), $type);
        $seed = $parent->seed($children->getLocalKeyName(), $type);
        $single = ($children instanceof HasOne || $children instanceof MorphOne) && ! $children->isOneOfMany();

        foreach ($this->nodes() as $node) {
            $removal = $this->removal($node);
            $check = match (true) {
                $removal !== null, in_array($node->operation, [MutationOperation::Update, MutationOperation::Detach, MutationOperation::Delete], true) => LinkCheck::Current,
                $single => LinkCheck::Single,
                default => LinkCheck::None,
            };
            $path = $check === LinkCheck::Single ? $node->path : $node->path.'.key';

            if ($node->operation === MutationOperation::Detach || $removal === ChildRemoval::Detach) {
                $replay->clear($path, $column);
            }

            if ($check === LinkCheck::Single) {
                $replay->single($path, $this->relation->acceptedOperations());
            }

            $rows = $replay->walk(
                $this->relation->resource(),
                $node,
                fn (LinkedRow $child): LinkStep => new LinkStep($path, $column, $child->identity, $child->link($column), $owner, self::unlinks($node), $check, $seed),
            );

            if ($removal !== null) {
                $replay->syncs->sync($path, $column, $owner, $rows);
            }
        }
    }

    #[\Override]
    public function check(Model $parent, WriteChecks $checks): void
    {
        foreach ($this->nodes() as $node) {
            $removal = $this->removal($node);

            if ($removal !== null) {
                $this->checkSync($parent, $node, $removal, $checks);

                continue;
            }

            $deletes = $node->operation === MutationOperation::Delete;
            $moves = ! in_array($node->operation, [MutationOperation::Create, MutationOperation::Update], true);
            $keys = $deletes ? new PlannedKeys : $this->planned($parent, $node->operation === MutationOperation::Detach);

            foreach ($checks->walk($this->relation->resource(), $node, $keys, $moves) as $child) {
                $checks->link($this->relation, $node, $parent, $child, self::unlinks($node));
            }
        }
    }

    private function checkSync(Model $parent, MutationNode $node, ChildRemoval $removal, WriteChecks $checks): void
    {
        $resource = $this->relation->resource();
        $listed = $checks->walk($resource, $node);
        $removed = $checks->children->plan($this->relation, $parent, $node, $listed);
        $removing = self::removing($node, $removal);
        $keys = $removal === ChildRemoval::Delete ? new PlannedKeys : $this->planned($parent, true);

        foreach ($checks->walk($resource, $removing, $keys, true, $removed) as $child) {
            $checks->link($this->relation, $removing, $parent, $child, true);
        }
    }

    private function sync(Model $parent, MutationNode $node, ChildRemoval $removal, Mutator $mutator, OperationLog $log): void
    {
        $resource = $this->relation->resource();
        $removing = self::removing($node, $removal);
        $keys = $this->keys($parent, true);

        foreach ($mutator->checks->children->written($node) as $child) {
            $mutator->remove($resource, $removing, $child, $keys);
        }

        $mutator->each($resource, $node, $log);
    }

    private function removal(MutationNode $node): ?ChildRemoval
    {
        return $node->operation === MutationOperation::Sync ? ChildRemoval::of($this->relation) : null;
    }

    private static function removing(MutationNode $node, ChildRemoval $removal): MutationNode
    {
        return new MutationNode($removal->operation(), null, null, null, null, null, $node->depth, null, $node->path);
    }

    private static function unlinks(MutationNode $node): bool
    {
        return $node->operation === MutationOperation::Detach || $node->operation === MutationOperation::Delete;
    }

    private function planned(Model $parent, bool $detach): PlannedKeys
    {
        $foreignKey = $this->children($parent)->getForeignKeyName();

        return new PlannedKeys($this->keys($parent, $detach), $detach ? [] : [$foreignKey => $parent]);
    }

    /** @return array<string, mixed> */
    private function keys(Model $parent, bool $detach): array
    {
        $children = $this->children($parent);
        $keys = [$children->getForeignKeyName() => $detach ? null : $children->getParentKey()];

        if (! $children instanceof MorphOneOrMany) {
            return $keys;
        }

        return [...$keys, $children->getMorphType() => $detach ? null : $parent->getMorphClass()];
    }

    /** @return HasOneOrMany<Model, Model, mixed> */
    private function children(Model $parent): HasOneOrMany
    {
        $children = $parent->{$this->relation->relation}();

        if (! $children instanceof HasOneOrMany) {
            throw new LogicException("The relation [{$this->relation->relation}] is not a HasOne, HasMany, MorphOne or MorphMany relation.");
        }

        return $children;
    }
}
