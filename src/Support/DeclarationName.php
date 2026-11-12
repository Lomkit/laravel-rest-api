<?php

namespace Lomkit\Rest\Support;

use Illuminate\Support\Str;

final readonly class DeclarationName
{
    /**
     * @param  class-string  $class
     * @param  class-string  $base
     */
    public static function of(?string $name, string $class, string $base): string
    {
        return $name ?: Str::of(class_basename($class))->beforeLast(class_basename($base))->snake(' ')->title()->toString();
    }

    public static function uriKey(?string $uriKey, string $name): string
    {
        return $uriKey ?: Str::slug($name, '-', null);
    }
}
