<?php

namespace Lomkit\Rest\Tests\Feature\Deletion\Stubs;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Events\TransactionBeginning;
use Illuminate\Database\Events\TransactionCommitted;
use Illuminate\Database\Events\TransactionRolledBack;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Lomkit\Rest\Tests\Fixtures\Archive\Document;
use RuntimeException;

final class DeletionTrace
{
    /** @var list<string> */
    public static array $lines = [];

    /** @var array<int, string|false> */
    public static array $denials = [];

    public static ?string $failure = null;

    public static function reset(): void
    {
        self::$lines = [];
        self::$denials = [];
        self::$failure = null;
    }

    public static function record(string $step, Model $model): void
    {
        $line = $step.' '.$model->getKey();
        self::$lines[] = $line;

        if (self::$failure === $line) {
            throw new RuntimeException("The step {$line} failed.");
        }
    }

    public static function listen(): void
    {
        Document::observe(AfterCommitDocumentObserver::class);
        DB::listen(fn (QueryExecuted $query) => self::$lines[] = 'sql '.preg_replace('/\w+\.updated_at = /', 'updated_at = ', str_replace(['"', '`'], '', $query->sql)));
        Event::listen(TransactionBeginning::class, fn (TransactionBeginning $event) => self::$lines[] = 'begin level '.$event->connection->transactionLevel());
        Event::listen(TransactionCommitted::class, fn (TransactionCommitted $event) => self::$lines[] = 'commit level '.$event->connection->transactionLevel());
        Event::listen(TransactionRolledBack::class, fn (TransactionRolledBack $event) => self::$lines[] = 'rollback level '.$event->connection->transactionLevel());

        foreach (['deleting', 'deleted', 'restoring', 'restored', 'forceDeleting', 'forceDeleted'] as $event) {
            Event::listen("eloquent.{$event}: ".Document::class, fn (Document $document) => self::$lines[] = "model {$event} {$document->getKey()}");
        }
    }

    /**
     * @param  list<string>  $steps
     * @return list<string>
     */
    public static function forRows(array $steps, int ...$ids): array
    {
        return array_merge(...array_map(
            fn (int $id): array => array_map(fn (string $step): string => str_replace('#', (string) $id, $step), $steps),
            array_values($ids),
        ));
    }
}
