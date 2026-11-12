<?php

namespace Lomkit\Rest\Http\Routing;

use Illuminate\Support\Str;
use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Exceptions\InvalidActionException;
use Lomkit\Rest\Exceptions\InvalidMetricException;
use Lomkit\Rest\Metrics\Metric;

enum DeclarationKind
{
    case Action;
    case Metric;

    /** @return class-string<Action|Metric> */
    public function base(): string
    {
        return match ($this) {
            self::Action => Action::class,
            self::Metric => Metric::class,
        };
    }

    /** @param class-string $class */
    public function displayName(?string $name, string $class): string
    {
        return $name ?: Str::of(class_basename($class))->beforeLast(class_basename($this->base()))->snake(' ')->title()->toString();
    }

    public function uriKey(string $displayName): string
    {
        return Str::slug($displayName, '-', null);
    }

    public function notDeclarable(string $class): InvalidActionException|InvalidMetricException
    {
        return match ($this) {
            self::Action => InvalidActionException::notAnAction($class),
            self::Metric => InvalidMetricException::notAMetric($class),
        };
    }

    public function dynamicKey(string $class, string $method): InvalidActionException|InvalidMetricException
    {
        return match ($this) {
            self::Action => InvalidActionException::dynamicKey($class, $method),
            self::Metric => InvalidMetricException::dynamicKey($class, $method),
        };
    }

    public function changedRoute(string $class): InvalidActionException|InvalidMetricException
    {
        return match ($this) {
            self::Action => InvalidActionException::changedRoute($class),
            self::Metric => InvalidMetricException::changedRoute($class),
        };
    }
}
