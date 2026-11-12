<?php

namespace Lomkit\Rest\Mutations;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Authorization\Changes;
use Lomkit\Rest\Authorization\Check;
use Lomkit\Rest\Fields\MutationOperation;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Query\AttributeWriter;
use Lomkit\Rest\Relations\Relation;

final class WriteChecks
{
    /** @var list<Check> */
    private array $checks = [];

    /** @var array<int, Changes> */
    private array $changes = [];

    /** @var array<int, Model> */
    private array $subjects = [];

    /** @var array<int, Model> */
    private array $parents = [];

    /** @var array<int, list<Check>> */
    private array $pending = [];

    private readonly WalkedRows $walked;

    public readonly PivotSet $pivots;

    public readonly SyncClaims $syncs;

    public function __construct(RowSet $rows)
    {
        $this->walked = new WalkedRows($rows);
        $this->pivots = new PivotSet;
        $this->syncs = new SyncClaims;
    }

    /** @param list<MutationNode> $nodes */
    public function authorize(Resource $resource, array $nodes): void
    {
        foreach ($nodes as $node) {
            $this->walk($resource, $node);
        }

        $this->plan($resource, $this->checks);
    }

    /** @return list<Model> */
    public function walk(Resource $resource, MutationNode $node, PlannedKeys $keys = new PlannedKeys, bool $moves = false): array
    {
        $rows = [];

        foreach ($this->walked->of($resource, $node) as $row) {
            $writes = RelationWrites::ofNode($resource, $row, $node);
            $attributes = $node->attributes ?? [];
            $planned = $keys;

            foreach ($writes as $write) {
                $planned = $planned->with($write->plannedKeys($row, $this));
            }

            $written = $planned->values;
            $changes = new Changes([...$attributes, ...$written], $planned->targets);
            $subject = $this->subjects[spl_object_id($row)] ?? $row;
            $preview = new AttributeWriter($resource->definition()->fields());
            $checked = $row->exists ? $subject : $preview->preview($row, $node->operation, $attributes, $written);
            $this->changes[spl_object_id($row)] = $changes;
            $this->parents[spl_object_id($row)] = $subject;
            $this->checks[] = $this->own($resource, $node, $checked, $changes, $moves);

            if ($row->exists && $changes->all() !== []) {
                $this->subjects[spl_object_id($row)] = $preview->preview(clone $subject, $node->operation, $attributes, $written);
            }

            foreach ($writes as $write) {
                $write->check($row, $this);
            }

            $rows[] = $row;
        }

        return $rows;
    }

    public function row(Resource $resource, mixed $key): ?Model
    {
        return $this->walked->row($resource, $key);
    }

    public function draft(Resource $resource, MutationNode $node): Model
    {
        return $this->walked->draft($resource, $node);
    }

    public function link(Relation $relation, MutationNode $node, Model $parent, Model $related, bool $detach): void
    {
        $ability = $relation->linkAbility($detach);
        $subject = $this->parents[spl_object_id($parent)] ?? $parent;

        if ($ability === null && $parent->exists) {
            $changes = $this->changes[spl_object_id($parent)] ?? new Changes([]);
            $this->checks[] = new Check($relation->parentResource(), 'update', $subject, $node->path, [$changes], $related);
        }

        if ($ability !== null && $parent->exists && $related->exists) {
            $this->checks[] = new Check($relation->parentResource(), $ability, $subject, $node->path, [$related], $related);
        }
    }

    public function linkNow(Relation $relation, MutationNode $node, Model $parent, Model $related, bool $detach): void
    {
        $ability = $relation->linkAbility($detach);

        if ($ability === null || ! (self::created($parent) || self::created($related))) {
            return;
        }

        $check = new Check($relation->parentResource(), $ability, $parent, $node->path, [$related], $related);

        if (! $parent->exists) {
            $this->pending[spl_object_id($parent)][] = $check;

            return;
        }

        $this->plan($relation->parentResource(), [$check]);
    }

    public function settle(Resource $resource, Model $model): void
    {
        $checks = $this->pending[spl_object_id($model)] ?? [];
        unset($this->pending[spl_object_id($model)]);

        if ($checks !== []) {
            $this->plan($resource, $checks);
        }
    }

    private function own(Resource $resource, MutationNode $node, Model $row, Changes $changes, bool $moves): Check
    {
        if ($node->operation === MutationOperation::Create) {
            $resource
                ->context()
                ->authorizer()
                ->ensurePlanned(Check::onClass($resource, 'create'));

            return new Check($resource, 'create', $row, $node->path);
        }

        if ($node->operation === MutationOperation::Delete) {
            return new Check($resource, 'delete', $row, $node->path, [], $row);
        }

        $updates = $node->operation === MutationOperation::Update || $moves || ($node->attributes ?? []) !== [];

        return $updates
            ? new Check($resource, 'update', $row, $node->path, [$changes], $row)
            : new Check($resource, 'view', $row, $node->path, [], $row);
    }

    /** @param list<Check> $checks */
    private function plan(Resource $resource, array $checks): void
    {
        $resource
            ->context()
            ->authorizer()
            ->plan($checks);
    }

    private static function created(Model $model): bool
    {
        return ! $model->exists || $model->wasRecentlyCreated;
    }
}
