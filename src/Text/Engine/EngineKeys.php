<?php

namespace Lomkit\Rest\Text\Engine;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

final readonly class EngineKeys
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

    /** @param Builder<Model> $query */
    public function constrain(Builder $query): void
    {
        $keys = array_keys($this->positions);

        if ($this->model->integerKeys()) {
            $query->whereIntegerInRaw($this->model->keyColumn(), $keys);
        } else {
            $query->whereIn($this->model->keyColumn(), array_map(fn (int|string $key): string => (string) $key, $keys));
        }

        $query->afterQuery(fn (mixed $result): mixed => $result instanceof Collection ? $this->keep($result) : $result);
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
