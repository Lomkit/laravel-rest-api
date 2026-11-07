<?php

namespace Lomkit\Rest\Tests\Feature\Events\Stubs;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Events\TransactionBeginning;
use Illuminate\Database\Events\TransactionCommitted;
use Illuminate\Database\Events\TransactionRolledBack;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use RuntimeException;

final class EventLog
{
    /** @var list<string> */
    public static array $lines = [];

    public static ?string $failure = null;

    /** @var list<object> */
    public static array $payloads = [];

    public static function reset(): void
    {
        self::$lines = [];
        self::$failure = null;
        self::$payloads = [];
    }

    public static function record(string $line): void
    {
        self::$lines[] = $line;

        if (self::$failure === strtok($line, ' ')) {
            throw new RuntimeException("The {$line} event failed.");
        }
    }

    public static function listen(): void
    {
        DB::listen(function (QueryExecuted $query): void {
            preg_match('/^(\w+)\s+(?:.*?\b(?:from|into)\s+)?(\w+)/', str_replace(['"', '`'], '', $query->sql), $match);

            self::$lines[] = "sql {$match[1]} {$match[2]}";
        });
        Event::listen(TransactionBeginning::class, fn (TransactionBeginning $event) => self::$lines[] = 'begin level '.$event->connection->transactionLevel());
        Event::listen(TransactionCommitted::class, fn (TransactionCommitted $event) => self::$lines[] = 'commit level '.$event->connection->transactionLevel());
        Event::listen(TransactionRolledBack::class, fn (TransactionRolledBack $event) => self::$lines[] = 'rollback level '.$event->connection->transactionLevel());
    }
}
