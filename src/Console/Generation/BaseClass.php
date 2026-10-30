<?php

namespace Lomkit\Rest\Console\Generation;

use Illuminate\Support\Str;

final readonly class BaseClass
{
    public function __construct(public string $fqcn, public string $command) {}

    public function isNamedLike(string $class): bool
    {
        return strcasecmp(class_basename($class), class_basename($this->fqcn)) === 0;
    }

    public function sharesNamespaceWith(string $class): bool
    {
        return Str::beforeLast($class, '\\') === Str::beforeLast($this->fqcn, '\\');
    }
}
