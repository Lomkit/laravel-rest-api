<?php

namespace Lomkit\Rest\Mutations;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use LogicException;
use Lomkit\Rest\Fields\MutationOperation;
use Lomkit\Rest\Relations\Relation;
use Lomkit\Rest\Support\Translation;

final class PivotSet
{
    /** @var array<string, AttachedRows> */
    private array $stored = [];

    /** @var array<string, list<array{array<array-key, Model>, array<array-key, Model>}>> */
    private array $moves = [];

    /** @var array<int, list<PivotPlan>> */
    private array $changes = [];

    /** @var array<int, list<Model>> */
    private array $drafts = [];

    /** @var array<int, array{Model, Model}> */
    private array $creations = [];

    /** @param list<Model> $related */
    public function apply(Relation $relation, Model $parent, MutationNode $node, array $related): PivotPlan
    {
        $slot = spl_object_id($parent).'.'.$relation->relation;
        $named = [];

        foreach ($related as $row) {
            $named[AttachedRows::key($row)] = $row;
        }

        if ($node->operation === MutationOperation::Create) {
            $this->drafts[spl_object_id($node)] = [...($this->drafts[spl_object_id($node)] ?? []), ...$related];
        }

        [$attached, $updated, $detached] = match ($node->operation) {
            MutationOperation::Create, MutationOperation::Attach => [$named, [], []],
            MutationOperation::Detach => [[], [], $named],
            default => $this->compare($relation, $parent, $slot, $node, $named),
        };
        $this->moves[$slot][] = [$attached, $detached];
        $plan = new PivotPlan(array_values($attached), array_values($updated), array_values($detached));
        $this->changes[spl_object_id($node)][] = $plan;

        return $plan;
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

    public function written(MutationNode $node): PivotPlan
    {
        $queue = $this->changes[spl_object_id($node)] ?? [];
        $plan = array_shift($queue) ?? throw new LogicException("The pivot rows of the entry [{$node->path}] are written before they are checked.");
        $this->changes[spl_object_id($node)] = $queue;

        return new PivotPlan($this->saved($plan->attached), $this->saved($plan->updated), $this->saved($plan->detached));
    }

    /**
     * @param  array<array-key, Model>  $named
     * @return array{array<array-key, Model>, array<array-key, Model>, array<array-key, Model>}
     */
    private function compare(Relation $relation, Model $parent, string $slot, MutationNode $node, array $named): array
    {
        $whole = $node->operation === MutationOperation::Sync && $node->withoutDetaching !== true;
        $max = $relation
            ->resource()
            ->context()
            ->limits()->totals->entries;
        $current = $this->current($relation, $parent, $slot, $named, $whole ? $max : null);
        $detached = match (true) {
            $node->operation === MutationOperation::Toggle => array_intersect_key($current, $named),
            $whole => array_diff_key($current, $named),
            default => [],
        };

        if (count($detached) > $max) {
            throw self::refusal($node, $max);
        }

        $updated = $node->operation !== MutationOperation::Toggle && ($node->pivot ?? []) !== []
            ? array_intersect_key($named, $current)
            : [];

        return [array_diff_key($named, $current), $updated, $detached];
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
