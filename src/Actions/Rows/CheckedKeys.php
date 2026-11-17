<?php

namespace Lomkit\Rest\Actions\Rows;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Fields\ID;

/** @internal */
final readonly class CheckedKeys
{
    /**
     * @param  array<int|string, true>  $targets
     * @param  array<int|string, true>  $rows
     */
    public function __construct(
        private ID $id,
        private array $targets,
        private array $rows,
    ) {}

    public function has(int|string|null $target): bool
    {
        return $target !== null && isset($this->targets[$target]);
    }

    public function covers(Model $model): bool
    {
        $key = $this->id->normalizedKey($model->getKey());

        return $key !== null && isset($this->rows[$key]);
    }

    public function count(): int
    {
        return count($this->targets);
    }
}
