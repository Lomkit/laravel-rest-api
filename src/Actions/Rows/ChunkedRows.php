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
        private int $chunkCount,
    ) {}

    /** @param Builder<Model> $query */
    public static function of(Builder $query, int $chunkCount, ?RowCap $cap): self
    {
        $rows = $query->clone()->reorder();

        $cap?->check($rows->toBase()->getCountForPagination());

        return new self($rows, $chunkCount);
    }

    #[\Override]
    public function each(Closure $each): int
    {
        $model = $this->query->getModel();
        $impacted = 0;

        $this->query->chunkById(max(1, $this->chunkCount), function (Collection $chunk) use ($each, &$impacted): void {
            $impacted += $each($chunk);
        }, $model->getQualifiedKeyName(), $model->getKeyName());

        return $impacted;
    }
}
