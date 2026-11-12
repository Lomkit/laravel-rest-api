<?php

namespace Lomkit\Rest\Mutations;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
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
            if ($node->operation === MutationOperation::Delete) {
                $mutator->delete($resource, $node, $log);

                continue;
            }

            if ($this->syncs($node)) {
                $mutator->each($resource, $node, $log);

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
        $syncs = [];
        $removed = [];

        foreach ($this->nodes() as $node) {
            $check = match (true) {
                $this->syncs($node), in_array($node->operation, [MutationOperation::Update, MutationOperation::Detach, MutationOperation::Delete], true) => LinkCheck::Current,
                $single => LinkCheck::Single,
                default => LinkCheck::None,
            };
            $path = $check === LinkCheck::Single ? $node->path : $node->path.'.key';

            if ($node->operation === MutationOperation::Detach) {
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

            if ($this->syncs($node)) {
                $syncs[$path] = $rows;
            }

            if (self::unlinks($node)) {
                $removed = [...$removed, ...$rows];
            }
        }

        foreach ($syncs as $path => $kept) {
            $replay->named->sync(new LinkedSync($path, $column, $owner, $kept, $removed));
        }
    }

    #[\Override]
    public function check(Model $parent, WriteChecks $checks): void
    {
        $syncs = [];
        $accounted = [];

        foreach ($this->nodes() as $node) {
            if ($this->syncs($node)) {
                $syncs[] = $node;
                $accounted = [...$accounted, ...$checks->walk($this->relation->resource(), $node)];

                continue;
            }

            $deletes = $node->operation === MutationOperation::Delete;
            $moves = ! in_array($node->operation, [MutationOperation::Create, MutationOperation::Update], true);
            $keys = $deletes ? new PlannedKeys : $this->planned($parent, $node->operation === MutationOperation::Detach);
            $children = $checks->walk($this->relation->resource(), $node, $keys, $moves);

            foreach ($children as $child) {
                $checks->link($this->relation, $node, $parent, $child, self::unlinks($node));
            }

            if (self::unlinks($node)) {
                $accounted = [...$accounted, ...$children];
            }
        }

        foreach ($syncs as $sync) {
            $checks->syncs->claim(new SyncClaim($this->relation, $this->children($parent), $sync->path, array_map(fn (Model $row): mixed => $row->getKey(), $accounted)));
        }
    }

    private function syncs(MutationNode $node): bool
    {
        return $node->operation === MutationOperation::Sync && $this->relation->syncsChildren();
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
