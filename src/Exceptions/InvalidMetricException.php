<?php

namespace Lomkit\Rest\Exceptions;

use LogicException;

final class InvalidMetricException extends LogicException
{
    public static function invalidType(string $metric, string $type): self
    {
        return new self("{$metric}::type() returns [{$type}]. A metric type is lowercase letters, digits and dashes, starting with a letter.");
    }

    public static function typeInResult(string $metric): self
    {
        return new self("{$metric}::calculate() returns a result with a [type] key. The response sets [type] from {$metric}::type(): rename the key of the result.");
    }

    public static function invalidRange(string $metric, string $key): self
    {
        return new self("{$metric}::ranges() declares the key [{$key}]. A range key is an integer greater than 0 and its label is a string.");
    }

    public static function unknownField(string $metric, string $helper, string $use, string $name, string $resource): self
    {
        return new self("{$metric}::{$helper}() {$use} [{$name}], which is not a field with a column on {$resource}.");
    }

    public static function wrongField(string $metric, string $helper, string $use, string $name, string $type, string $expected): self
    {
        return new self("{$metric}::{$helper}() {$use} [{$name}], whose type is [{$type}]: it needs {$expected} field.");
    }

    public static function invalidLimit(string $metric, int $limit): self
    {
        return new self("{$metric} keeps [{$limit}] parts. A partition limit is an integer greater than 0.");
    }
}
