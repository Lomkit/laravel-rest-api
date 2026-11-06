<?php

namespace Lomkit\Rest\Mutations;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use LogicException;
use Lomkit\Rest\Fields\MutationOperation;
use Lomkit\Rest\Relations\Relation;

final class PivotSet
{
    /** @var array<string, array<array-key, Model>> */
    private array $sets = [];

    /** @var array<string, list<array{array<array-key, Model>, array<array-key, Model>}>> */
    private array $moves = [];

    /** @var array<int, list<array{list<Model>, list<Model>}>> */
    private array $changes = [];

    /** @var array<int, list<Model>> */
    private array $drafts = [];

    /** @var array<int, array{Model, Model}> */
    private array $creations = [];

    /**
     * @param  list<Model>  $related
     * @return array{list<Model>, list<Model>}
     */
    public function apply(Relation $relation, Model $parent, MutationNode $node, array $related): array
    {
        $slot = spl_object_id($parent).'.'.$relation->relation;
        $named = [];

        foreach ($related as $row) {
            $named[self::key($row)] = $row;
        }

        if ($node->operation === MutationOperation::Create) {
            $this->drafts[spl_object_id($node)] = [...($this->drafts[spl_object_id($node)] ?? []), ...$related];
        }

        if (! in_array($node->operation, [MutationOperation::Sync, MutationOperation::Toggle], true)) {
            $this->moves[$slot][] = $node->operation === MutationOperation::Detach ? [[], $named] : [$named, []];

            return $node->operation === MutationOperation::Detach ? [[], $related] : [$related, []];
        }

        $current = $this->current($relation, $parent, $slot);
        $attached = array_diff_key($named, $current);
        $detached = match (true) {
            $node->operation === MutationOperation::Toggle => array_intersect_key($current, $named),
            $node->withoutDetaching === true => [],
            default => array_diff_key($current, $named),
        };
        $this->moves[$slot][] = [$attached, $detached];
        $changes = [array_values($attached), array_values($detached)];
        $this->changes[spl_object_id($node)][] = $changes;

        return $changes;
    }

    public function created(MutationNode $node, Model $model): void
    {
        $queue = $this->drafts[spl_object_id($node)] ?? [];
        $draft = array_shift($queue);
        $this->drafts[spl_object_id($node)] = $queue;

        if ($draft !== null) {
            $this->creations[spl_object_id($draft)] = [$draft, $model];
        }
    }

    /** @return array{list<Model>, list<Model>} */
    public function written(MutationNode $node): array
    {
        $queue = $this->changes[spl_object_id($node)] ?? [];
        [$attached, $detached] = array_shift($queue) ?? [[], []];
        $this->changes[spl_object_id($node)] = $queue;

        return [$this->saved($attached), $this->saved($detached)];
    }

    /**
     * @param  list<Model>  $rows
     * @return list<Model>
     */
    private function saved(array $rows): array
    {
        return array_map(fn (Model $row): Model => ($this->creations[spl_object_id($row)] ?? [$row, $row])[1], $rows);
    }

    /** @return array<array-key, Model> */
    private function current(Relation $relation, Model $parent, string $slot): array
    {
        $current = $this->sets[$slot] ?? $this->attached($relation, $parent);

        foreach ($this->moves[$slot] ?? [] as [$attached, $detached]) {
            $current = array_diff_key($current + $attached, $detached);
        }

        $this->moves[$slot] = [];

        return $this->sets[$slot] = $current;
    }

    /** @return array<array-key, Model> */
    private function attached(Relation $relation, Model $parent): array
    {
        if (! $parent->exists) {
            return [];
        }

        $pivots = $parent->{$relation->relation}();

        if (! $pivots instanceof BelongsToMany) {
            throw new LogicException("The relation [{$relation->relation}] is not a BelongsToMany, MorphToMany or MorphedByMany relation.");
        }

        $rows = [];

        foreach ($pivots->get() as $row) {
            $rows[self::key($row)] = $row;
        }

        return $rows;
    }

    private static function key(Model $row): string
    {
        if (! $row->exists) {
            return 'new#'.spl_object_id($row);
        }

        $key = $row->getKey();

        return is_int($key) || is_string($key) ? (string) $key : throw new LogicException($row::class.' has a key that cannot name a pivot row.');
    }
}
