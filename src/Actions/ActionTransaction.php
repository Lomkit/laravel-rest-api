<?php

namespace Lomkit\Rest\Actions;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Lomkit\Rest\Http\WriteTransaction;

enum ActionTransaction
{
    case Chunk;
    case Whole;
    case Row;
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

    /** @param Collection<array-key, Model> $models */
    public function handle(Action $action, ActionFields $fields, WriteTransaction $transaction, Collection $models): void
    {
        if ($this === self::Row) {
            foreach ($models as $row) {
                $action->beforeRow($fields, $row);
                $transaction->over([$row])->run(fn () => $action->handleRow($fields, $row));
            }

            return;
        }

        if ($this === self::Chunk) {
            $transaction->over($models)->run(fn () => $action->handle($fields, $models));

            return;
        }

        $action->handle($fields, $models);
    }
}
