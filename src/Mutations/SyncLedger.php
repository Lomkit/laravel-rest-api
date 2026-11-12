<?php

namespace Lomkit\Rest\Mutations;

use Lomkit\Rest\Validation\Violation;

final class SyncLedger
{
    /** @var list<array{string, LinkColumn, string, array<string, true>}> */
    private array $syncs = [];

    /** @var array<string, LinkedRow> */
    private array $named = [];

    /** @param list<LinkedRow> $listed */
    public function sync(string $path, LinkColumn $column, string $owner, array $listed): void
    {
        $identities = array_map(fn (LinkedRow $row): string => $row->identity, $listed);
        $this->syncs[] = [$path, $column, $owner, array_fill_keys($identities, true)];
    }

    public function name(LinkedRow $row): void
    {
        $this->named[$row->identity] = $row;
    }

    /** @return array<string, Violation> */
    public function violations(): array
    {
        $violations = [];

        foreach ($this->syncs as [$path, $column, $owner, $listed]) {
            foreach ($this->named as $identity => $row) {
                if (! isset($listed[$identity]) && $column->links($row->model, $owner)) {
                    $violations[$path] ??= Violation::namedBesideSync($path);
                }
            }
        }

        return $violations;
    }
}
