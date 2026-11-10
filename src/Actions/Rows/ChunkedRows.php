<?php

namespace Lomkit\Rest\Actions\Rows;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

final readonly class ChunkedRows implements ActionRows
{
    /** @param Builder<Model> $query */
    private function __construct(
        private Builder $query,
        private ChunkSize $size,
        private ?int $count,
    ) {}

    /** @param Builder<Model> $query */
    public static function of(Builder $query, ChunkSize $size, ?RowCap $cap): self
    {
        $rows = $query->clone()->reorder();

        if ($cap === null) {
            return new self($rows, $size, null);
        }

        $count = $rows->toBase()->getCountForPagination();
        $cap->check($count);

        return new self($rows, $size, $count);
    }

    #[\Override]
    public function each(Closure $each): int
    {
        $model = $this->query->getModel();
        $impacted = 0;

        $this->query->chunkById($this->size->for($this->count(...)), function (Collection $chunk) use ($each, &$impacted): void {
            $impacted += $each($chunk);
        }, $model->getQualifiedKeyName(), $model->getKeyName());

        return $impacted;
    }

    #[\Override]
    public function count(): int
    {
        return $this->count ?? $this->query->toBase()->getCountForPagination();
    }

    #[\Override]
    public function only(CheckedKeys $keys): ActionRows
    {
        return new CheckedRows($this, $keys);
    }
}
