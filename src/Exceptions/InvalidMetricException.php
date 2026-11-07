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
}
