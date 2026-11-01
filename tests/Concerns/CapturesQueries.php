<?php

namespace Lomkit\Rest\Tests\Concerns;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;

trait CapturesQueries
{
    /** @var list<array{string, array<array-key, mixed>}> */
    private array $statements = [];

    private function captureQueries(): void
    {
        $this->statements = [];

        DB::listen(function (QueryExecuted $query): void {
            $this->statements[] = [str_replace(['`', '"', '[', ']'], '', $query->sql), $query->bindings];
        });
    }

    /** @return list<array{string, array<array-key, mixed>}> */
    private function keyQueries(string $table, string $key = 'id'): array
    {
        return array_values(array_filter(
            $this->statements,
            fn (array $statement): bool => str_starts_with($statement[0], "select {$table}.{$key} from {$table}"),
        ));
    }

    /** @return list<array{string, array<array-key, mixed>}> */
    private function readsOf(string $table): array
    {
        return array_values(array_filter(
            $this->statements,
            fn (array $statement): bool => str_starts_with($statement[0], 'select') && str_contains($statement[0], " from {$table}"),
        ));
    }
}
