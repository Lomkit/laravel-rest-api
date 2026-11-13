<?php

namespace Lomkit\Rest\Exceptions;

use LogicException;
use Lomkit\Rest\Instructions\Instruction;

final class InvalidInstructionException extends LogicException
{
    public static function engineOnly(string $instruction): self
    {
        return new self("{$instruction} implements AppliesToEngine without its own handle(): it only applies to a text search on a search engine.");
    }

    public static function queryOnly(string $instruction): self
    {
        return new self("{$instruction} does not implement AppliesToEngine: it cannot apply to a text search on a search engine.");
    }

    public static function notAnInstruction(string $resource, string $entry): self
    {
        return new self("{$resource}::instructions() lists [{$entry}], which is not a class that extends ".Instruction::class.'.');
    }

    public static function baseListed(string $resource): self
    {
        return new self("{$resource}::instructions() lists ".Instruction::class.' itself. List a class that extends it.');
    }

    public static function notInstantiable(string $resource, string $class): self
    {
        return new self("{$resource}::instructions() lists {$class}, which is abstract or cannot be instantiated. List a concrete class.");
    }

    public static function foreignBinding(string $resource, string $class, string $bound): self
    {
        return new self("The container builds [{$bound}] for {$class}, listed in {$resource}::instructions(): bind {$class} to itself or to a class that extends it.");
    }
}
