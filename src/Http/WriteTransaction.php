<?php

namespace Lomkit\Rest\Http;

use Closure;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Eloquent\Model;

final readonly class WriteTransaction
{
    /** @param list<RowState> $rows */
    private function __construct(
        private ConnectionInterface $connection,
        private int $attempts,
        private array $rows = [],
    ) {}

    public static function of(Resource $resource): self
    {
        return new self($resource::newModel()->getConnection(), $resource->context()->config()->transactionAttempts);
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

    private function restore(): void
    {
        foreach ($this->rows as $row) {
            $row->restore();
        }
    }
}
