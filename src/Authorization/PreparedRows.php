<?php

namespace Lomkit\Rest\Authorization;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Exceptions\InvalidAuthorizationExtensionException;

/** @internal */
final readonly class PreparedRows
{
    /** @param list<RowState> $states */
    private function __construct(private array $states) {}

    /** @param Collection<int, Model> $rows */
    public static function of(Collection $rows, LoadedRelationNames $names): self
    {
        return new self(array_map(
            static fn (Model $row): RowState => RowState::of($row, $names->forRow($row)),
            array_values($rows->all()),
        ));
    }

    public function ensureUnchanged(string $name, string $class, PendingChecks $checks): void
    {
        if (! $this->keeps($checks->rows)) {
            throw InvalidAuthorizationExtensionException::changedRows($name, $class, $checks->resource::class, $checks->ability);
        }
    }

    /** @param Collection<int, Model> $rows */
    public function keeps(Collection $rows): bool
    {
        $rows = array_values($rows->all());

        if (count($rows) !== count($this->states)) {
            return false;
        }

        foreach ($rows as $index => $row) {
            if (! $this->states[$index]->keeps($row)) {
                return false;
            }
        }

        return true;
    }
}
