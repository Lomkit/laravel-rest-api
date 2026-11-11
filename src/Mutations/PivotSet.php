<?php

namespace Lomkit\Rest\Mutations;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Lomkit\Rest\Fields\MutationOperation;
use Lomkit\Rest\Relations\Relation;
use Lomkit\Rest\Support\Translation;

final class PivotSet
{
    /** @var array<string, AttachedRows> */
    private array $stored = [];

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
            $named[AttachedRows::key($row)] = $row;
        }

        if ($node->operation === MutationOperation::Create) {
            $this->drafts[spl_object_id($node)] = [...($this->drafts[spl_object_id($node)] ?? []), ...$related];
        }

        if (! in_array($node->operation, [MutationOperation::Sync, MutationOperation::Toggle], true)) {
            $this->moves[$slot][] = $node->operation === MutationOperation::Detach ? [[], $named] : [$named, []];

            return $node->operation === MutationOperation::Detach ? [[], $related] : [$related, []];
        }

        $whole = $node->operation === MutationOperation::Sync && $node->withoutDetaching !== true;
        $max = $relation
            ->resource()
            ->context()
            ->limits()->totals->entries;
        $current = $this->current($relation, $parent, $slot, $named, $whole ? $max : null);
        $attached = array_diff_key($named, $current);
        $detached = match (true) {
            $node->operation === MutationOperation::Toggle => array_intersect_key($current, $named),
            $whole => array_diff_key($current, $named),
            default => [],
        };

        if (count($detached) > $max) {
            throw self::refusal($node, $max);
        }

        $rewritten = $node->operation === MutationOperation::Sync && ($node->pivot ?? []) !== []
            ? array_intersect_key($named, $current)
            : [];
        $this->moves[$slot][] = [$attached, $detached];
        $changes = [array_values($attached + $rewritten), array_values($detached)];
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

    /**
     * @param  array<array-key, Model>  $named
     * @return array<array-key, Model>
     */
    private function current(Relation $relation, Model $parent, string $slot, array $named, ?int $max): array
    {
        $stored = $this->stored[$slot] ??= new AttachedRows($relation, $parent);
        $moves = $this->moves[$slot] ?? [];
        $current = $max === null
            ? $stored->among($named)
            : $stored->all($max + count($named) + array_sum(array_map(fn (array $move): int => count($move[1]), $moves)) + 1);

        foreach ($moves as [$attached, $detached]) {
            $current = array_diff_key($current + $attached, $detached);
        }

        return $current;
    }

    private static function refusal(MutationNode $node, int $max): ValidationException
    {
        $key = $node->path.'.key';

        return ValidationException::withMessages([$key => [Translation::line('rest::validation.sync_detached_total', ['attribute' => $key, 'max' => (string) $max])]]);
    }
}
