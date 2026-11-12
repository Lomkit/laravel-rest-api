<?php

namespace Lomkit\Rest\Mutations;

use Lomkit\Rest\Fields\MutationOperation;
use Lomkit\Rest\Validation\Violation;

final class NamedRows
{
    /** @var array<string, int> */
    private array $counts = [];

    /** @var array<string, LinkedRow> */
    private array $rows = [];

    /** @var list<DeletedRow> */
    private array $deleted = [];

    /** @var list<LinkedSync> */
    private array $syncs = [];

    public function name(MutationNode $node, LinkedRow $row): void
    {
        if ($node->operation === MutationOperation::Create) {
            return;
        }

        $this->counts[$row->identity] = ($this->counts[$row->identity] ?? 0) + 1;
        $this->rows[$row->identity] = $row;

        if ($node->operation === MutationOperation::Delete) {
            $this->deleted[] = new DeletedRow($node->path.'.key', $row->identity);
        }
    }

    public function sync(LinkedSync $sync): void
    {
        $this->syncs[] = $sync;
    }

    /**
     * @param  list<LinkStep>  $steps
     * @return array<string, Violation>
     */
    public function violations(array $steps): array
    {
        $violations = [];

        foreach ($this->syncs as $sync) {
            foreach ($steps as $step) {
                if ($sync->contradictedBy($step)) {
                    $violations[$step->path] ??= Violation::keptBySync($step->path);
                }
            }

            foreach ($this->rows as $row) {
                if ($sync->leavesOut($row)) {
                    $violations[$sync->path] ??= Violation::namedBesideSync($sync->path);
                }
            }
        }

        foreach ($this->deleted as $deleted) {
            if ($this->counts[$deleted->identity] > 1 && ! $this->kept($deleted->identity)) {
                $violations[$deleted->path] ??= Violation::repeated($deleted->path);
            }
        }

        return $violations;
    }

    private function kept(string $identity): bool
    {
        return array_any($this->syncs, fn (LinkedSync $sync): bool => $sync->keeps($identity));
    }
}
