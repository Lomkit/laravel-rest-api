<?php

namespace Lomkit\Rest\Console\Generation;

use Illuminate\Support\Str;
use Throwable;

final readonly class LoadFailure
{
    public static function describe(Throwable $error, string $basePath): string
    {
        $file = Str::chopStart($error->getFile(), (realpath($basePath) ?: $basePath).DIRECTORY_SEPARATOR);

        return "{$error->getMessage()} ({$file}:{$error->getLine()})";
    }
}
