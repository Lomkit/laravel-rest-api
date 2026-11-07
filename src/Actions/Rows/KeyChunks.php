<?php

namespace Lomkit\Rest\Actions\Rows;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Fields\ID;

use function Illuminate\Support\enum_value;

final readonly class KeyChunks implements ActionRows
{
    /**
     * @param  Builder<Model>  $rows
     * @param  list<int|string>  $keys
     */
    private function __construct(
        private Builder $rows,
        private ID $id,
        private array $keys,
        private int $chunkCount,
    ) {}

    /**
     * @param  Builder<Model>  $query
     * @param  list<int|string>  $keys
     */
    public static function of(Builder $query, ID $id, array $keys, int $chunkCount): self
    {
        $rows = $query
            ->clone()
            ->reorder()
            ->limit(null);

        return new self($rows, $id, $keys, $chunkCount);
    }

    /** @param Builder<Model> $query */
    public static function read(Builder $query, ID $id, int $chunkCount, ?int $limit, RowCap $cap): self
    {
        $values = $query
            ->clone()
            ->limit($cap->limit($limit))
            ->pluck($query->getModel()->getQualifiedKeyName());
        $keys = [];

        foreach ($values as $value) {
            $key = $id->parseKey(enum_value($value));

            if ($key !== null) {
                $keys[$id->normalizeKey($key)] ??= $key;
            }
        }

        $cap->check(count($keys));

        return self::of($query, $id, array_values($keys), $chunkCount);
    }

    #[\Override]
    public function each(Closure $each): int
    {
        $impacted = 0;

        foreach (array_chunk($this->keys, max(1, $this->chunkCount)) as $keys) {
            $impacted += $each($this->load($keys));
        }

        return $impacted;
    }

    /**
     * @param  list<int|string>  $keys
     * @return Collection<int, Model>
     */
    private function load(array $keys): Collection
    {
        $found = [];

        foreach ($this->id->whereKeys($this->rows->clone(), $keys)->get() as $row) {
            $key = $this->id->parseKey(enum_value($row->getKey()));

            if ($key !== null) {
                $found[$this->id->normalizeKey($key)] = $row;
            }
        }

        return new Collection(array_values(array_filter(array_map(fn (int|string $key): ?Model => $found[$this->id->normalizeKey($key)] ?? null, $keys))));
    }
}
