<?php

namespace Lomkit\Rest\Actions;

use Closure;
use Illuminate\Database\ConnectionInterface;

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
    public function aroundRun(ConnectionInterface $connection, Closure $run): mixed
    {
        return $this === self::Whole ? $connection->transaction($run) : $run();
    }

    /** @param Closure(): void $chunk */
    public function aroundChunk(ConnectionInterface $connection, Closure $chunk): void
    {
        $this === self::Chunk ? $connection->transaction($chunk) : $chunk();
    }
}
