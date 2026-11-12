<?php

namespace Lomkit\Rest\Exceptions;

use LogicException;
use Lomkit\Rest\Metrics\Metric;

final class InvalidMetricException extends LogicException
{
    public static function invalidType(string $metric, string $type): self
    {
        return new self("{$metric}::type() returns [{$type}]. A metric type is lowercase letters, digits and dashes, starting with a letter.");
    }

    public static function notAMetric(string $class): self
    {
        return new self("[{$class}] is listed in metrics(), but it does not extend ".Metric::class.'.');
    }

    public static function dynamicKey(string $metric, string $method): self
    {
        return new self("{$metric} is listed by its class, but it overrides {$method}(): its route key must be read without building it. Set \$name, or list the metric as an instance.");
    }

    public static function changedRoute(string $metric): self
    {
        return new self("{$metric} is listed by its class, but its built instance has another route key or other middleware than its class declares. Set \$name, \$middleware and \$withoutMiddleware as property defaults, or list the metric as an instance.");
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

    public static function invalidPercentile(string $metric, string $helper, float $fraction): self
    {
        return new self("{$metric}::{$helper}() takes the percentile [{$fraction}]. A percentile is a number from 0 to 1, for example 0.9.");
    }

    public static function invalidCacheDuration(string $metric, int $seconds): self
    {
        return new self("{$metric} caches its result for [{$seconds}] seconds. Cache a metric for 1 second or more, for example ->cacheFor(600).");
    }

    public static function invalidCacheScope(string $metric, string $scope): self
    {
        return new self("{$metric} caches its result for the scope [{$scope}]. A cache scope is user or global.");
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

    public static function invalidRowLimit(string $metric, int $limit, int $max): self
    {
        return new self("{$metric} keeps [{$limit}] rows. A table limit is an integer from 1 to {$max}.");
    }

    public static function withoutColumns(string $metric): self
    {
        return new self("{$metric}::rows() has no column. A table needs at least one TableColumn.");
    }

    public static function invalidColumn(string $metric, string $name): self
    {
        return new self("{$metric}::rows() names a column [{$name}]. A column name is letters, digits and underscores, starting with a lowercase letter.");
    }

    /** @param list<string> $columns */
    public static function unknownOrder(string $metric, string $orderBy, array $columns): self
    {
        $names = implode(', ', $columns);

        return new self("{$metric}::rows() orders by [{$orderBy}], which is not one of its columns: {$names}.");
    }
}
