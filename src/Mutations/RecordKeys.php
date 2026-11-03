<?php

namespace Lomkit\Rest\Mutations;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Search\Plan\KeySet;

use function Illuminate\Support\enum_value;

final readonly class RecordKeys implements KeySet
{
    /** @param list<int|string|null> $keys */
    private function __construct(
        private ID $id,
        private array $keys,
    ) {}

    /** @param list<OperationEntry> $operations */
    public static function of(ID $id, array $operations): self
    {
        return new self($id, array_map(static fn (OperationEntry $operation): int|string|null => $id->parseKey(enum_value($operation->writtenKeys()[0] ?? null)), $operations));
    }

    #[\Override]
    public function constrain(Builder $query): void
    {
        if (! $query instanceof EloquentBuilder) {
            throw new InvalidArgumentException('Record keys constrain the Eloquent builder of the mutated resource, not '.get_debug_type($query).'.');
        }

        $this->id->whereKeys($query, array_values(array_unique(array_filter($this->keys, static fn (int|string|null $key): bool => $key !== null))));
    }

    /**
     * @param  Collection<int, Model>  $rows
     * @return list<Model|null>
     */
    public function align(Collection $rows): array
    {
        $found = [];

        foreach ($rows as $row) {
            $key = $this->id->parseKey(enum_value($row->getKey()));

            if ($key !== null) {
                $found[$this->id->normalizeKey($key)] = $row;
            }
        }

        return array_map(fn (int|string|null $key): ?Model => $key === null ? null : $found[$this->id->normalizeKey($key)] ?? null, $this->keys);
    }
}
