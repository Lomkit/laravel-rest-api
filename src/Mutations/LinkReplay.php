<?php

namespace Lomkit\Rest\Mutations;

use Closure;
use Lomkit\Rest\Fields\MutationOperation;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Validation\Violation;

final class LinkReplay
{
    /** @var list<LinkStep> */
    private array $steps = [];

    private int $drafts = 0;

    /** @var array<string, Violation>|null */
    private ?array $violations = null;

    private ?Resource $resource = null;

    /** @var list<MutationNode> */
    private array $nodes = [];

    /** @var array<string, int> */
    private array $named = [];

    /** @var list<array{string, string}> */
    private array $deleted = [];

    /** @var array<string, LinkColumn> */
    private array $cleared = [];

    public function __construct(private readonly RowSet $rows) {}

    /** @param list<MutationNode> $nodes */
    public function plan(Resource $resource, array $nodes): void
    {
        $this->resource = $resource;
        $this->nodes = $nodes;
    }

    public function violation(string $path): ?Violation
    {
        $this->violations ??= $this->replay();

        return $this->violations[$path] ?? null;
    }

    /**
     * @param  (Closure(LinkedRow): LinkStep)|null  $saved
     * @return list<LinkedRow>
     */
    public function walk(Resource $resource, MutationNode $node, ?Closure $saved = null): array
    {
        $rows = $this->rowsOf($resource, $node);

        foreach ($rows as $row) {
            $this->name($node, $row);
            $writes = RelationWrites::ofNode($resource, $row->model, $node);
            $from = count($this->steps);

            foreach ($writes as $write) {
                $write->rehearseBefore($row, $this);
            }

            if ($saved !== null) {
                $this->save($saved($row), $from);
            }

            foreach ($writes as $write) {
                $write->rehearseAfter($row, $this);
            }
        }

        return $rows;
    }

    public function add(LinkStep $step): void
    {
        $this->steps[] = $step;
    }

    public function clear(string $path, LinkColumn $column): void
    {
        $this->cleared[$path] = $column;
    }

    private function save(LinkStep $link, int $from): void
    {
        $steps = array_slice($this->steps, 0, $from);

        foreach (array_slice($this->steps, $from) as $step) {
            $steps[] = $step->column->id === $link->column->id && $step->child === $link->child ? $step->overridden() : $step;
        }

        $this->steps = [...$steps, $link];
    }

    /** @return array<string, Violation> */
    private function replay(): array
    {
        if ($this->resource === null) {
            return [];
        }

        foreach ($this->nodes as $node) {
            $this->walk($this->resource, $node);
        }

        $ledger = new LinkLedger;
        $ledger->seed($this->steps);
        $violations = [];

        foreach ($this->steps as $step) {
            if (! $ledger->passes($step)) {
                $violations[$step->path] ??= $step->check === LinkCheck::Single ? Violation::heldRecord($step->path) : Violation::notLinked($step->path);
            }
        }

        foreach ($this->deleted as [$path, $identity]) {
            if ($this->named[$identity] > 1) {
                $violations[$path] ??= Violation::repeated($path);
            }
        }

        $columns = new NullableColumns;

        foreach ($this->cleared as $path => $column) {
            if (! $column->nullable($columns)) {
                $violations[$path] ??= Violation::requiredLink($path);
            }
        }

        return $violations;
    }

    private function name(MutationNode $node, LinkedRow $row): void
    {
        if ($node->operation === MutationOperation::Create) {
            return;
        }

        $this->named[$row->identity] = ($this->named[$row->identity] ?? 0) + 1;

        if ($node->operation === MutationOperation::Delete) {
            $this->deleted[] = [$node->path.'.key', $row->identity];
        }
    }

    /** @return list<LinkedRow> */
    private function rowsOf(Resource $resource, MutationNode $node): array
    {
        if ($node->operation === MutationOperation::Create) {
            return [LinkedRow::draft($resource::newModel(), ++$this->drafts)];
        }

        $rows = [];

        foreach (is_array($node->key) ? $node->key : [$node->key] as $key) {
            $row = $key === null ? null : $this->rows->peek($resource, $key);

            if ($row !== null) {
                $rows[] = LinkedRow::stored($row);
            }
        }

        return $rows;
    }
}
