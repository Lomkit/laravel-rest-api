<?php

namespace Lomkit\Rest\Mutations;

final class LinkLedger
{
    /** @var array<string, array<string, ?string>> */
    private array $links = [];

    /** @param list<LinkStep> $steps */
    public function seed(array $steps): void
    {
        $owners = [];
        $columns = [];

        foreach ($steps as $step) {
            if ($step->check === LinkCheck::Single && $step->seed !== null) {
                $owners[$step->column->id][$step->owner] = $step->seed;
                $columns[$step->column->id] = $step->column;
            }
        }

        foreach ($columns as $id => $column) {
            $this->links[$id] = $column->children($owners[$id]);
        }
    }

    public function passes(LinkStep $step): bool
    {
        $links = $this->links[$step->column->id] ?? [];
        $current = array_key_exists($step->child, $links) ? $links[$step->child] : $step->current;

        $passes = match ($step->check) {
            LinkCheck::None => true,
            LinkCheck::Current => $current === $step->owner,
            LinkCheck::Single => $this->others($step) === [],
        };

        if ($passes && $step->moves) {
            $this->links[$step->column->id][$step->child] = $step->next();
        }

        return $passes;
    }

    /** @return list<string> */
    private function others(LinkStep $step): array
    {
        $others = [];

        foreach ($this->links[$step->column->id] ?? [] as $child => $owner) {
            if ($owner === $step->owner && $child !== $step->child) {
                $others[] = $child;
            }
        }

        return $others;
    }
}
