<?php

namespace Lomkit\Rest\Tests\Feature\Deletion\Stubs;

use Illuminate\Database\Eloquent\Model;
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
}
