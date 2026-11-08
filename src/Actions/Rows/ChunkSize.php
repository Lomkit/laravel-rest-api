<?php

namespace Lomkit\Rest\Actions\Rows;

use Closure;
use Illuminate\Contracts\Queue\ShouldQueue;
use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Validation\Limits;

final readonly class ChunkSize
{
    /** @param positive-int $rows */
    private function __construct(
        private int $rows,
        private ?int $jobs,
    ) {}

    public static function of(Action $action, Limits $limits): self
    {
        return new self(max(1, $action->chunkCount), $action instanceof ShouldQueue ? $limits->sizes->jobs : null);
    }

    public static function fixed(int $rows): self
    {
        return new self(max(1, $rows), null);
    }

    /**
     * @param  Closure(): int  $count
     * @return positive-int
     */
    public function for(Closure $count): int
    {
        if ($this->jobs === null) {
            return $this->rows;
        }

        return max($this->rows, intdiv($count() + $this->jobs - 1, $this->jobs));
    }
}
