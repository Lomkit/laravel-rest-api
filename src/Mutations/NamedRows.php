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

    /** @var list<array{string, string}> */
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
            $this->deleted[] = [$node->path.'.key', $row->identity];
        }
    }

    public function sync(LinkedSync $sync): void
    {
        $this->syncs[] = $sync;
    }

    /** @return array<string, Violation> */
    public function violations(): array
    {
        $violations = [];

        foreach ($this->deleted as [$path, $identity]) {
            if ($this->counts[$identity] > 1) {
                $violations[$path] ??= Violation::repeated($path);
            }
        }

        foreach ($this->syncs as $sync) {
            foreach ($this->rows as $row) {
                if ($sync->leavesOut($row)) {
                    $violations[$sync->path] ??= Violation::namedBesideSync($sync->path);
                }
            }
        }

        return $violations;
    }
}
