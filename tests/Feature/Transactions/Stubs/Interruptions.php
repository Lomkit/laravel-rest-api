<?php

namespace Lomkit\Rest\Tests\Feature\Transactions\Stubs;

use Illuminate\Database\QueryException;
use PDOException;
use Throwable;

class Interruptions
{
    /** @var list<Throwable|null> */
    public static array $throws = [];

    /** @var list<string> */
    public static array $runs = [];

    /** @param list<Throwable|null> $throws */
    public static function reset(array $throws): void
    {
        self::$throws = $throws;
        self::$runs = [];
    }

    public static function deadlock(): QueryException
    {
        return new QueryException('secondary', 'update "documents" set "title" = ?', ['Renamed'], new PDOException('SQLSTATE[40001]: Serialization failure: 1213 Deadlock found when trying to get lock; try restarting transaction'));
    }

    public static function run(string $run): void
    {
        self::$runs[] = $run;
        $throw = array_shift(self::$throws);

        if ($throw !== null) {
            throw $throw;
        }
    }
}
