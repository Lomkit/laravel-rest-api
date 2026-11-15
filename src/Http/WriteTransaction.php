<?php

namespace Lomkit\Rest\Http;

use Closure;
use Illuminate\Container\Container;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseTransactionRecord;
use Illuminate\Database\DatabaseTransactionsManager;
use Illuminate\Database\Eloquent\Model;

final readonly class WriteTransaction
{
    /**
     * @param  positive-int  $attempts
     * @param  list<RowState>  $rows
     */
    private function __construct(
        private Connection $connection,
        private int $attempts,
        private array $rows = [],
    ) {}

    public static function of(Resource $resource): self
    {
        return new self($resource::newModel()->getConnection(), max(1, $resource->context()->config()->transactionAttempts));
    }

    /** @param iterable<Model> $rows */
    public function over(iterable $rows): self
    {
        $states = [];

        foreach ($rows as $row) {
            $states[] = RowState::of($row);
        }

        return new self($this->connection, $this->attempts, $states);
    }

    /**
     * @template TResult
     *
     * @param  Closure(): TResult  $write
     * @return TResult
     */
    public function run(Closure $write): mixed
    {
        $attempt = 0;

        return $this->connection->transaction(function () use ($write, &$attempt): mixed {
            if ($attempt++ > 0) {
                $this->restore();
            }

            return $write();
        }, $this->attempts);
    }

    /** @param Closure(): void $callback */
    public function afterCommit(Closure $callback): void
    {
        $container = Container::getInstance();
        $transactions = $container->bound('db.transactions') ? $container->make('db.transactions') : null;

        if (! $transactions instanceof DatabaseTransactionsManager) {
            $this->connection->afterCommit($callback);

            return;
        }

        $pending = $transactions->callbackApplicableTransactions()
            ->last(fn (DatabaseTransactionRecord $record): bool => $record->connection === $this->connection->getName());

        if ($pending === null) {
            $callback();

            return;
        }

        $pending->addCallback($callback);
    }

    private function restore(): void
    {
        foreach ($this->rows as $row) {
            $row->restore();
        }
    }
}
