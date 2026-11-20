<?php

namespace Lomkit\Rest\Text\Engine\Totals;

use Lomkit\Rest\Exceptions\InvalidTextSearchException;
use Lomkit\Rest\Fields\Boolean;
use Lomkit\Rest\Fields\Enum;
use Lomkit\Rest\Fields\Field;
use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Search\Totals\MeasureType;
use Lomkit\Rest\Text\Engine\IndexAttribute;
use Lomkit\Rest\Text\Engine\Profiles\ComputesTotals;
use Lomkit\Rest\Text\Engine\Profiles\EngineProfile;
use Lomkit\Rest\Text\Engine\Profiles\ReservesSortAttributes;
use Lomkit\Rest\Text\TextTarget;

/** @internal */
final readonly class TotalsAttributes
{
    /**
     * @param  array<string, IndexAttribute>  $filterable
     * @param  array<mixed>  $groupable
     * @param  array<mixed>  $measurable
     */
    public static function of(TextTarget $target, EngineProfile $profile, array $filterable, array $groupable, array $measurable): ?TotalsGrammar
    {
        if (! $profile instanceof ComputesTotals) {
            if ($groupable !== [] || $measurable !== []) {
                throw InvalidTextSearchException::untotalledEngine($target->resource, $profile->name());
            }

            return null;
        }

        if ($groupable !== [] || $measurable !== []) {
            $profile->ensureTotalsUsableBy($target);
        }

        $groups = [];
        $measures = [];
        $scales = [];

        foreach (self::names($target, $filterable, $groupable, 'groupable') as $name) {
            $groups[] = self::group($target, $profile, $filterable[$name]->field);
        }

        foreach (self::names($target, $filterable, $measurable, 'measurable') as $name) {
            $measures[$name] = self::measure($target, $profile, $filterable[$name]->field);
            $scale = DecimalScale::of($target, $profile, $filterable[$name]->field);

            if ($scale !== null) {
                $scales[$name] = $scale;
            }
        }

        return new TotalsGrammar($groups, $measures, $scales);
    }

    /**
     * @param  array<string, IndexAttribute>  $filterable
     * @param  array<mixed>  $declared
     * @return list<string>
     */
    private static function names(TextTarget $target, array $filterable, array $declared, string $declaration): array
    {
        $names = [];

        foreach (array_values($declared) as $index => $name) {
            if (! is_string($name)) {
                throw InvalidTextSearchException::notAnAttributeName($target->resource, $index, $name);
            }

            if (! isset($filterable[$name])) {
                throw $declaration === 'groupable' ? InvalidTextSearchException::ungroupableAttribute($target->resource, $name) : InvalidTextSearchException::unmeasurableAttribute($target->resource, $name);
            }

            if (in_array($name, $names, true)) {
                throw InvalidTextSearchException::duplicateAttribute($target->resource, $name);
            }

            $names[] = $name;
        }

        return $names;
    }

    private static function group(TextTarget $target, ComputesTotals $profile, Field $field): string
    {
        if (! $field instanceof Enum && ! $field instanceof Boolean && ! $field instanceof Text && ! $field instanceof Integer && ! $field instanceof ID || ($profile instanceof ReservesSortAttributes && in_array($field->name, $profile->reservedSorts(), true))) {
            throw InvalidTextSearchException::ungroupableAttributeType($target->resource, $field->name, $field->type(), $profile->name());
        }

        return $field->name;
    }

    /** @return list<MeasureType> */
    private static function measure(TextTarget $target, ComputesTotals $profile, Field $field): array
    {
        $types = $profile->measureTypes($field);

        if ($types === []) {
            throw InvalidTextSearchException::unmeasurableAttributeType($target->resource, $field->name, $field->type(), $profile->name());
        }

        return $types;
    }
}
