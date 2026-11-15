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

    public function ensureUnchanged(string $extension, PendingChecks $checks): void
    {
        $rows = array_values($checks->rows->all());

        if (count($rows) !== count($this->states)) {
            throw InvalidAuthorizationExtensionException::changedRows($extension, $checks->resource::class, $checks->ability);
        }

        foreach ($rows as $index => $row) {
            if (! $this->states[$index]->keeps($row)) {
                throw InvalidAuthorizationExtensionException::changedRows($extension, $checks->resource::class, $checks->ability);
            }
        }
    }
}
