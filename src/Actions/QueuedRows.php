<?php

namespace Lomkit\Rest\Actions;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use LogicException;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Query\Clauses\PerimeterClause;
use Lomkit\Rest\Query\Clauses\TrashedClause;
use Lomkit\Rest\Query\Trashed;
use Lomkit\Rest\Validation\KeysGrammar;
use Stringable;

use function Illuminate\Support\enum_value;

final readonly class QueuedRows
{
    /** @param list<int|string> $keys */
    private function __construct(
        public array $keys,
        public ?Trashed $trashed,
        public bool $perimeter,
    ) {}

    /** @param array<array-key, Model> $models */
    public static function of(array $models, ?Trashed $trashed, bool $perimeter): self
    {
        return new self(array_values(array_map(self::keyOf(...), $models)), $trashed, $perimeter);
    }

    /** @return Collection<int, Model> */
    public function load(Resource $resource): Collection
    {
        if ($this->keys === []) {
            return new Collection;
        }

        $query = $resource::newModel()
            ->newQuery()
            ->useWritePdo();
        TrashedClause::of($this->trashed)?->apply($query);

        if ($this->perimeter) {
            (new PerimeterClause($resource))->apply($query);
        }

        $rows = KeysGrammar::idOf($resource)
            ->whereKeys($query, $this->keys)
            ->get()
            ->keyBy(fn (Model $model): string => (string) self::keyOf($model));

        return new Collection(array_values(array_filter(array_map(fn (int|string $key): ?Model => $rows->get((string) $key), $this->keys))));
    }

    private static function keyOf(Model $model): int|string
    {
        $key = enum_value($model->getKey());

        return match (true) {
            is_int($key), is_string($key) => $key,
            $key instanceof Stringable => (string) $key,
            default => throw new LogicException('A row without an integer or string key cannot be queued.'),
        };
    }
}
