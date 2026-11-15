<?php

namespace Lomkit\Rest\Authorization;

use Illuminate\Database\Eloquent\Model;

/** @internal */
final readonly class DependentVersions
{
    /** @param array<string, string|null> $parts */
    public function __construct(private array $parts = []) {}

    public static function rowOf(Model $row): string
    {
        return Check::rowsOf($row)[0];
    }

    public function of(Model $row): ?string
    {
        $id = self::rowOf($row);

        return array_key_exists($id, $this->parts) ? $this->parts[$id] : '';
    }
}
