<?php

namespace Lomkit\Rest\Actions;

use Closure;
use Lomkit\Rest\Http\WriteTransaction;

enum ActionTransaction
{
    case Chunk;
    case Whole;
    case None;

    /**
     * @template TResult
     *
     * @param  Closure(): TResult  $run
     * @return TResult
     */
    public function aroundRun(WriteTransaction $transaction, Closure $run): mixed
    {
        return $this === self::Whole ? $transaction->run($run) : $run();
    }

    /** @param Closure(): void $chunk */
    public function aroundChunk(WriteTransaction $transaction, Closure $chunk): void
    {
        $this === self::Chunk ? $transaction->run($chunk) : $chunk();
    }
}
