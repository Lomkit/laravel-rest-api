<?php

namespace Lomkit\Rest\Authorization;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/** @internal */
final readonly class LoadedRelationNames
{
    /** @param array<int, array<array-key, mixed>> $byRow */
    private function __construct(private array $byRow) {}

    /** @param Collection<int, Model> $rows */
    public static function of(Collection $rows): self
    {
        $byRow = [];

        foreach ($rows->all() as $row) {
            $byRow[spl_object_id($row)] = LoadedRelations::names($row);
        }

        return new self($byRow);
    }

    /** @return array<array-key, mixed> */
    public function forRow(Model $row): array
    {
        return $this->byRow[spl_object_id($row)] ?? LoadedRelations::names($row);
    }
}
