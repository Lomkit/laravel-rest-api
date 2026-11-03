<?php

namespace Lomkit\Rest\Text\Engine;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Lomkit\Rest\Search\Plan\KeySet;

final readonly class EngineKeys implements KeySet
{
    /** @param array<int|string, int> $positions */
    private function __construct(
        private ScoutModel $model,
        private array $positions,
    ) {}

    /** @param array<mixed> $keys */
    public static function of(ScoutModel $model, array $keys): self
    {
        $positions = [];

        foreach ($model->keys($keys) as $position => $key) {
            $positions[$key] ??= $position;
        }

        return new self($model, $positions);
    }

    #[\Override]
    public function constrain(Builder $query): void
    {
        if (! $query instanceof EloquentBuilder) {
            throw new InvalidArgumentException('Engine keys constrain the Eloquent builder of the searched resource, not '.get_debug_type($query).'.');
        }

        $keys = array_keys($this->positions);

        if ($this->model->integerKeys()) {
            $query->whereIntegerInRaw($this->model->keyColumn(), $keys);
        } else {
            $query->whereIn($this->model->keyColumn(), array_map(fn (int|string $key): string => (string) $key, $keys));
        }

        $query->afterQuery(fn (mixed $result): mixed => $result instanceof Collection ? $this->keep($result) : $result);
    }

    #[\Override]
    public function columns(): array
    {
        return [$this->model->keyColumn()];
    }

    /**
     * @param  Collection<array-key, Model>  $models
     * @return Collection<int, Model>
     */
    public function keep(Collection $models): Collection
    {
        return $models
            ->filter(fn (Model $model): bool => isset($this->positions[$this->model->keyOf($model)]))
            ->sortBy(fn (Model $model): int => $this->positions[$this->model->keyOf($model)])
            ->values();
    }
}
