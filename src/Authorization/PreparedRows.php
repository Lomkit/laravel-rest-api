<?php

namespace Lomkit\Rest\Authorization;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Exceptions\InvalidAuthorizationExtensionException;

/** @internal */
final readonly class PreparedRows
{
    /** @param list<array{int, array<string, mixed>, list<string>, array<array-key, int|null>}> $states */
    private function __construct(private array $states) {}

    /** @param Collection<int, Model> $rows */
    public static function of(Collection $rows): self
    {
        return new self(array_map(self::state(...), array_values($rows->all())));
    }

    public function ensureUnchanged(string $extension, PendingChecks $checks): void
    {
        $rows = array_values($checks->rows->all());

        if (count($rows) !== count($this->states)) {
            throw InvalidAuthorizationExtensionException::changedRows($extension, $checks->resource::class, $checks->ability);
        }

        foreach ($rows as $index => $row) {
            if (! self::keeps($this->states[$index], self::state($row))) {
                throw InvalidAuthorizationExtensionException::changedRows($extension, $checks->resource::class, $checks->ability);
            }
        }
    }

    /** @return array{int, array<string, mixed>, list<string>, array<array-key, int|null>} */
    private static function state(Model $row): array
    {
        $relations = array_map(static fn (mixed $related): ?int => is_object($related) ? spl_object_id($related) : null, $row->getRelations());

        return [spl_object_id($row), $row->getRawOriginal(), array_keys($row->getDirty()), $relations];
    }

    /**
     * @param  array{int, array<string, mixed>, list<string>, array<array-key, int|null>}  $before
     * @param  array{int, array<string, mixed>, list<string>, array<array-key, int|null>}  $after
     */
    private static function keeps(array $before, array $after): bool
    {
        [$row, $original, $dirty, $relations] = $before;

        if ($row !== $after[0] || $original !== $after[1] || $dirty !== $after[2]) {
            return false;
        }

        foreach ($relations as $name => $related) {
            if (! array_key_exists($name, $after[3]) || $after[3][$name] !== $related) {
                return false;
            }
        }

        return true;
    }
}
