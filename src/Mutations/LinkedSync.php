<?php

namespace Lomkit\Rest\Mutations;

final readonly class LinkedSync
{
    /** @var array<string, true> */
    private array $kept;

    /** @var array<string, true> */
    private array $accounted;

    /**
     * @param  list<LinkedRow>  $kept
     * @param  list<LinkedRow>  $removed
     */
    public function __construct(
        public string $path,
        private LinkColumn $column,
        private string $owner,
        array $kept,
        array $removed,
    ) {
        $this->kept = self::identities($kept);
        $this->accounted = [...$this->kept, ...self::identities($removed)];
    }

    public function leavesOut(LinkedRow $row): bool
    {
        return ! isset($this->accounted[$row->identity]) && $this->column->links($row->model, $this->owner);
    }

    public function keeps(string $identity): bool
    {
        return isset($this->kept[$identity]);
    }

    public function contradictedBy(LinkStep $step): bool
    {
        return $step->moves && $step->column->id === $this->column->id && $this->keeps($step->child) && $step->next() !== $this->owner;
    }

    /**
     * @param  list<LinkedRow>  $rows
     * @return array<string, true>
     */
    private static function identities(array $rows): array
    {
        return array_fill_keys(array_map(fn (LinkedRow $row): string => $row->identity, $rows), true);
    }
}
