<?php

namespace Lomkit\Rest\Console\Generation;

use Illuminate\Support\Str;

enum MissingResourceHint
{
    case GenerateTheResource;
    case ExposeTheModel;

    public function for(string $model, string $option): string
    {
        return match ($this) {
            self::GenerateTheResource => 'Generate one first with php artisan rest:resource '.class_basename($model)."Resource --model={$option} --generate, then run this command again with --force.",
            self::ExposeTheModel => "Rerun with --model={$option} to expose ".Str::plural(Str::lower(Str::headline(class_basename($model)))).'.',
        };
    }
}
