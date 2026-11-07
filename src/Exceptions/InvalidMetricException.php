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
        return new self("{$metric} keeps [{$limit}] groups. A limit is an integer greater than 0.");
    }

    public static function invalidWeekStart(string $metric, int $day): self
    {
        return new self("{$metric} starts its weeks on [{$day}]. A week starts on a day from 0 (Sunday) to 6 (Saturday).");
    }

    public static function trendWithoutRanges(string $metric): self
    {
        return new self("{$metric} declares no ranges(). A trend counts its periods with the range of the request: declare at least one range.");
    }

    public static function withoutPeriods(string $metric, string $helper): self
    {
        return new self("{$metric}::{$helper}() has no number of periods: pass one, or declare ranges() and run the metric from its route.");
    }

    public static function unsupportedDriver(string $driver): self
    {
        return new self("Metrics group rows by period on sqlite, mysql, mariadb and pgsql connections, not on [{$driver}].");
    }
}
