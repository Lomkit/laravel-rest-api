<?php

namespace Lomkit\Rest\Exceptions\Concerns;

trait RefusesEngineTotals
{
    public static function ungroupableAttribute(string $resource, string $attribute): self
    {
        return new self("The resource [{$resource}] declares [{$attribute}] in ScoutEngine::groupable(), which filterable() does not declare. Declare the attribute in filterable() first: groupable() takes names from that list, never from indexAttributes().");
    }

    public static function unmeasurableAttribute(string $resource, string $attribute): self
    {
        return new self("The resource [{$resource}] declares [{$attribute}] in ScoutEngine::measurable(), which filterable() does not declare. Declare the attribute in filterable() first: measurable() takes names from that list, never from indexAttributes().");
    }

    public static function ungroupableAttributeType(string $resource, string $attribute, string $type, string $engine): self
    {
        return new self("The resource [{$resource}] declares the {$type} attribute [{$attribute}] in ScoutEngine::groupable(), which the engine [{$engine}] does not group. Group an Enum, Boolean, Text, Integer or ID attribute.");
    }

    public static function unmeasurableAttributeType(string $resource, string $attribute, string $type, string $engine): self
    {
        return new self("The resource [{$resource}] declares the {$type} attribute [{$attribute}] in ScoutEngine::measurable(), which the engine [{$engine}] does not measure. Measure an Integer or Number attribute.");
    }

    public static function untotalledEngine(string $resource, string $engine): self
    {
        return new self("The resource [{$resource}] declares ScoutEngine::groupable() or measurable(), but its engine [{$engine}] computes no totals. Remove these declarations, or search the resource with an engine that computes totals.");
    }
}
