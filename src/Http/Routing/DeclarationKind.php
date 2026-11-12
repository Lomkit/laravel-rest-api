<?php

namespace Lomkit\Rest\Http\Routing;

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

    public function notDeclarable(string $class): InvalidActionException|InvalidMetricException
    {
        return match ($this) {
            self::Action => InvalidActionException::notAnAction($class),
            self::Metric => InvalidMetricException::notAMetric($class),
        };
    }

    public function baseListed(): InvalidActionException|InvalidMetricException
    {
        return match ($this) {
            self::Action => InvalidActionException::baseListed(),
            self::Metric => InvalidMetricException::baseListed(),
        };
    }

    public function notInstantiable(string $class): InvalidActionException|InvalidMetricException
    {
        return match ($this) {
            self::Action => InvalidActionException::notInstantiable($class),
            self::Metric => InvalidMetricException::notInstantiable($class),
        };
    }

    public function dynamicKey(string $class, string $method): InvalidActionException|InvalidMetricException
    {
        return match ($this) {
            self::Action => InvalidActionException::dynamicKey($class, $method),
            self::Metric => InvalidMetricException::dynamicKey($class, $method),
        };
    }

    public function foreignBinding(string $class, string $bound): InvalidActionException|InvalidMetricException
    {
        return match ($this) {
            self::Action => InvalidActionException::foreignBinding($class, $bound),
            self::Metric => InvalidMetricException::foreignBinding($class, $bound),
        };
    }

    public function reboundRoute(string $class, string $bound): InvalidActionException|InvalidMetricException
    {
        return match ($this) {
            self::Action => InvalidActionException::reboundRoute($class, $bound),
            self::Metric => InvalidMetricException::reboundRoute($class, $bound),
        };
    }

    public function changedRoute(string $class): InvalidActionException|InvalidMetricException
    {
        return match ($this) {
            self::Action => InvalidActionException::changedRoute($class),
            self::Metric => InvalidMetricException::changedRoute($class),
        };
    }

    public function staleRoute(string $class): InvalidActionException|InvalidMetricException
    {
        return match ($this) {
            self::Action => InvalidActionException::staleRoute($class),
            self::Metric => InvalidMetricException::staleRoute($class),
        };
    }
}
