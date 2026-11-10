<?php

namespace Lomkit\Rest\Actions\Rows;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

final readonly class CheckedRows implements ActionRows
{
    public function __construct(
        private ActionRows $rows,
        private CheckedKeys $keys,
    ) {}

    #[\Override]
    public function each(Closure $each): int
    {
        return $this->rows->each(fn (Collection $models): int => $each($models
            ->filter(fn (Model $model): bool => $this->keys->has($model->getKey()))
            ->values()));
    }

    #[\Override]
    public function count(): int
    {
        return $this->keys->count();
    }

    #[\Override]
    public function only(CheckedKeys $keys): ActionRows
    {
        return new self($this->rows, $keys);
    }
}
