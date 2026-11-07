<?php

namespace Lomkit\Rest\Actions\Rows;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

final readonly class PagedRows implements ActionRows
{
    /** @param Builder<Model> $query */
    public function __construct(
        private Builder $query,
        private int $chunkCount,
    ) {}

    #[\Override]
    public function each(Closure $each): int
    {
        $limit = $this->query->toBase()->limit;
        $impacted = 0;

        $this->query
            ->clone()
            ->chunk($this->chunkCount, function (Collection $chunk, int $page) use ($limit, $each, &$impacted): bool {
                if ($limit !== null && $page * $this->chunkCount >= $limit) {
                    $impacted += $each($chunk->take($limit - ($page - 1) * $this->chunkCount));

                    return false;
                }

                $impacted += $each($chunk);

                return true;
            });

        return $impacted;
    }
}
